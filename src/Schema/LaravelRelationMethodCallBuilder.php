<?php

namespace Migrator\Schema;

use Migrator\Schema\Exceptions\InverseMethodNotFound;
use Migrator\Schema\Exceptions\MultipleModelsWithSameShortName;

class LaravelRelationMethodCallBuilder
{
    /**
     * @var MethodCommand
     */
    private $method;

    public function __construct(MethodCommand $method)
    {
        $this->method = $method;
    }

    /**
     * @throws InverseMethodNotFound
     * @throws MultipleModelsWithSameShortName
     *
     * @return string
     */
    public function build()
    {
        if ($this->method->isBelongsTo()) {
            return $this->forBelongsTo();
        }

        if ($this->method->isHasOne()) {
            return $this->forHasOneOrMany('hasOne');
        }

        if ($this->method->isHasMany()) {
            return $this->forHasOneOrMany('hasMany');
        }

        if ($this->method->isHasMany()) {
            return $this->forHasOneOrMany('hasMany');
        }

        if ($this->method->isManyToMany()) {
            return $this->forManyToMany();
        }

        if ($this->method->isHasManyThrough()) {
            return $this->forHasManyThrough();
        }

        if ($this->method->isPolymorphic()) {
            return $this->forPolymorphic();
        }

        if ($this->method->isMorphMany()) {
            return $this->forMorphMany();
        }

        if ($this->method->isMorphToMany()) {
            return $this->forMorphToMany();
        }

        if ($this->method->isMorphedByMany()) {
            return $this->forMorphedByMany();
        }

        $cls = get_class($this);

        throw new \InvalidArgumentException("Implement {$cls}::build() for {$this->method->relationType()}");
    }

    /**
     * @throws InverseMethodNotFound
     * @throws MultipleModelsWithSameShortName
     *
     * @return string
     */
    private function forBelongsTo(): string
    {
        $invModel = $this->method->inverseMethod()->getModel();
        $invClass = $invModel->getNamespace().$invModel->getShortName();

        $belongsField = '';
        $otherPrimaryField = '';
        $pk = $invModel->getPrimaryKeyFieldNamesExpectOne("belongsTo {$this->method->humanName()} requires simple one-field primary key on {$invModel->getShortName()}");

        if (!$this->method->isBelongsFieldDefault() || $pk != 'id') {
            $belongsField = $this->commaQuotedArg($this->method->belongsToFieldName());
        }

        if ($this->method->selfReferencing()) {
            $belongsField = $this->commaQuotedArg($this->method->belongsToFieldName());
        }

        if ($pk != 'id') {
            $otherPrimaryField = $this->commaQuotedArg($pk);
        }

        return "belongsTo($invClass::class{$belongsField}{$otherPrimaryField})";
    }

    /**
     * @param $typ
     *
     * @throws InverseMethodNotFound
     * @throws MultipleModelsWithSameShortName
     *
     * @return string
     */
    private function forHasOneOrMany($typ): string
    {
        $ourModel = $this->method->getModel();
        $invMethod = $this->method->inverseMethod();
        $invModel = $invMethod->getModel();
        $invClass = $invModel->getNamespace().$invModel->getShortName();

        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::hasOne() */
        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::hasMany() */
        $foreignKey = '';
        $localKey = '';
        $e = "{$typ} {$this->method->humanName()} requires simple one-field primary key on {$ourModel->getShortName()}";
        $pk = $ourModel->getPrimaryKeyFieldNamesExpectOne($e);

        if (!$this->method->isBelongsFieldDefault() || $pk != 'id') {
            $foreignKey = $this->commaQuotedArg($invMethod->belongsToFieldName());
        }

        if ($pk != 'id') {
            $localKey = $this->commaQuotedArg($pk);
        }

        return "{$typ}($invClass::class{$foreignKey}{$localKey})";
    }

    private function forManyToMany()
    {
        $ourModel = $this->method->getModel();
        $invMethod = $this->method->inverseMethod();
        $invModel = $invMethod->getModel();
        $invClass = $invModel->getNamespace().$invModel->getShortName();

        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::belongsToMany() */
        $pivotTableName = $this->method->getPivotTableName();
        $table = $this->commaQuotedArg($pivotTableName);

        $e = "Many to Many {$this->method->humanName()} requires simple one-field primary key on {$ourModel->getShortName()}";
        $parentKeyValue = $ourModel->getPrimaryKeyFieldNamesExpectOne($e);
        $parentKey = $this->commaQuotedArg($parentKeyValue, 'id');

        $e = "Many to Many {$invMethod->humanName()} requires simple one-field primary key on {$invModel->getShortName()}";
        $relatedKeyValue = $invModel->getPrimaryKeyFieldNamesExpectOne($e);
        $relatedKey = $this->commaQuotedArg($relatedKeyValue, 'id');

        $shouldNotExplicitlySet = $parentKeyValue == 'id';
        $foreignPivotKey = $this->commaQuotedArg($invMethod->ourKeyInPivot($shouldNotExplicitlySet));

        $shouldNotExplicitlySet = $relatedKeyValue == 'id';
        $relatedPivotKey = $this->commaQuotedArg($this->method->ourKeyInPivot($shouldNotExplicitlySet));

        // TODO: this one is a mystery
        $relation = $this->commaQuotedArg(null);

        $using = '';
        $withPivot = '';
        if ($pivotModel = $this->method->getSchema()->getModelByTableName($pivotTableName)) {
            $using = '->using('.$pivotModel->getNamespace().$pivotModel->getShortName().'::class)';
            $withPivot = '->withPivot("'.implode('", "', $pivotModel->getFieldsNames()).'")';
        }

        $as = '';
        if ($asName = $this->method->getAs()) {
            $asName = addslashes($asName);
            $as = "->as('$asName')";
        }

        $withTimeStamps = '';
        if ($asName = $this->method->isPivotWithTimestamps()) {
            $withTimeStamps = '->withTimestamps()';
        }

        return $this->removeNulls('belongsToMany('.
            "$invClass::class{$table}{$foreignPivotKey}{$relatedPivotKey}{$parentKey}{$relatedKey}{$relation}".
            "){$using}{$withPivot}{$as}{$withTimeStamps}");
    }

    /**
     * @throws InverseMethodNotFound
     * @throws MultipleModelsWithSameShortName
     *
     * @return null|string|string[]
     */
    private function forHasManyThrough()
    {
        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::hasManyThrough() */
        // example goes for $this->method = Country.posts() (via User.posts)

        $ourModel = $this->method->getModel(); // Country
        $invMethod = $this->method->inverseMethod(); // User.country()

        $throughMethod = $this->method->hasManyThroughIntermediateMethod(); // User.posts()
        $throughModel = $throughMethod->getModel();
        $throughClass = $throughModel->getNamespace().$throughModel->getShortName(); // User

        $endModel = $throughMethod->inverseMethod()->getModel();
        $endClass = $endModel->getNamespace().$endModel->getShortName(); // Post

        $relatedCls = $endClass.'::class'; // Post
        $throughCls = ', '.$throughClass.'::class'; // User

        // 'country_id', // Foreign key on users table...
        $firstKey = $this->commaQuotedArg($invMethod->belongsToFieldName());
        // 'user_id', // Foreign key on posts table...
        $secondKey = $this->commaQuotedArg($throughMethod->inverseMethod()->belongsToFieldName());

        $but = "Many to Many {$this->method->humanName()} requires simple one-field primary key on {$ourModel->getShortName()}";
        // 'id', // Local key on countries table...
        $localKey = $this->commaQuotedArg($ourModel->getPrimaryKeyFieldNamesExpectOne($but), 'id');

        $but = "Many to Many {$this->method->humanName()} requires simple one-field primary key on {$throughModel->getShortName()}";
        // 'id' // Local key on users table...
        $secondLocalKey = $this->commaQuotedArg($throughModel->getPrimaryKeyFieldNamesExpectOne($but), 'id');

        return $this->removeNulls('hasManyThrough('.
            "{$relatedCls}{$throughCls}{$firstKey}{$secondKey}{$localKey}{$secondLocalKey}".
            ')');
    }

    /**
     * @param $value
     *
     * @return string
     */
    private function commaQuotedArg($value, $nullIfEqualsThis = null): string
    {
        if ($value === null || $value === $nullIfEqualsThis) {
            return ', null';
        }
        $e = addslashes($value);

        return ", '$e'";
    }

    private function removeNulls($string)
    {
        $rx = '/, null\)/';
        while (preg_match($rx, $string)) {
            $string = preg_replace($rx, ')', $string);
        }

        return $string;
    }

    private function forPolymorphic()
    {
        return 'morphTo()';
    }

    private function forMorphMany()
    {
        $ourModel = $this->method->getModel(); // Post
        $invMethod = $this->method->inverseMethod(); // Comment.commentable()
        $invModel = $invMethod->getModel(); // Comment
        $invClass = $invModel->getNamespace().$invModel->getShortName(); // Comment

        /** @see Following example at https://laravel.com/docs/5.6/eloquent-relationships#polymorphic-relations */
        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::morphMany() */
        $related = $invClass.'::class';
        $ableName = $invMethod->getName();
        $name = $this->commaQuotedArg($ableName); // commentable
        $type = $this->commaQuotedArg("{$ableName}_type");
        $id = $this->commaQuotedArg("{$ableName}_id");

        $but = "MorphOne {$this->method->humanName()} requires simple one-field primary key on {$ourModel->getShortName()}";
        $localKey = $this->commaQuotedArg($ourModel->getPrimaryKeyFieldNamesExpectOne($but));

        return $this->removeNulls("morphMany({$related}{$name}{$type}{$id}{$localKey})");
    }

    private function forMorphToMany()
    {
        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::morphToMany */
        /** @see Following example at https://laravel.com/docs/5.6/eloquent-relationships#polymorphic-relations */
        // we are folling example for Post.tags()

        $endModel = $this->method->getSchema()->getModel($this->method->getReturnTypeSingle()); // Tag
        $endClass = $endModel->getNamespace().$endModel->getShortName(); // \..\Tag
        $related = $endClass.'::class';

        $name = $this->commaQuotedArg($this->method->inverseMethod()->getName());
        $table = $this->commaQuotedArg($this->method->inverseMethod()->getModel()->getTableName());
        $foreignPivotKey = $this->commaQuotedArg(null);
        $relatedPivotKey = $this->commaQuotedArg(null);
        $parentKey = $this->commaQuotedArg(null);
        $relatedKey = $this->commaQuotedArg(null);

        // $this->morphToMany('App\Tag', 'taggable');
        return $this->removeNulls("morphToMany({$related}{$name}{$table}{$foreignPivotKey}{$relatedPivotKey}{$parentKey}{$relatedKey})");
    }

    private function forMorphedByMany()
    {
        /** @see \Illuminate\Database\Eloquent\Concerns\HasRelationships::morphedByMany */
        /** @see Following example at https://laravel.com/docs/5.6/eloquent-relationships#polymorphic-relations */
        // we are folling example for Tag.posts()

        $endModel = $this->method->getSchema()->getModel($this->method->getReturnTypeSingle()); // Tag
        $endClass = $endModel->getNamespace().$endModel->getShortName(); // \..\Tag
        $related = $endClass.'::class';

        $invModel = $this->method->inverseMethod()->getModel();
        $invPolymorphicMethodName = $invModel->findMethodsByReturnType($this->method->getReturnTypeSingle())[0]->getName();

        $name = $this->commaQuotedArg($invPolymorphicMethodName);
        $table = $this->commaQuotedArg(null); // $invModel->getTableName()

        $foreignPivotKey = $this->commaQuotedArg(null);
        $relatedPivotKey = $this->commaQuotedArg(null);
        $parentKey = $this->commaQuotedArg(null);
        $relatedKey = $this->commaQuotedArg(null);

        if (['id'] != $this->method->inverseMethod()->getModel()->getPrimaryKeyFieldNames()) {
            throw new \RuntimeException("Currently, we don't support polymorphic many-to-many with custom Primary Key see LaravelRelationMethodCallBuilder method `forMorphedByMany()` for explanation");
        }
        // for some reason if taggables has `tag_pk` key, no matter what args I put here, this happens:
        // table taggables has no column named tag_tag_pk (SQL: insert into "taggables" ("tag_tag_pk", "taggable_id", "taggable_type")
        // and I can't seem to affect this `tag_tag_pk` (it's tag_pk)

        return $this->removeNulls("morphedByMany({$related}{$name}{$table}{$foreignPivotKey}{$relatedPivotKey}{$parentKey}{$relatedKey})");
    }
}
