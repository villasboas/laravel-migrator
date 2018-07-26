<?php

namespace Migrator\Tests;

use Migrator\Schema\Exceptions\MethodNotFound;
use Migrator\Schema\FieldCommand;
use Migrator\Schema\MethodCommand;
use Migrator\Schema\ModelCommand;
use Migrator\Schema\Parser;
use Migrator\Schema\Schema;

class ParserTest extends BaseTestCase
{
    /** @var Schema */
    public $parsed;

    protected function setUp()
    {
        parent::setUp();
    }

    /** @test */
    public function parses_has_one()
    {
        $this->parse('
            User
                name: string
                other_name: string
                phone() via phone_id
                other_phone() via other_phone_id
                
            Phone
                phone_number: string
                user()
                
                RENAME FIELD old_phone TO phone_number
        ');

        $this->assertCount(2, $this->parsed->getModels());

        $this->assertCount(2, $this->parsed->getModel('\App\User')->getMethods());
        $this->assertCount(5, $this->parsed->getModel('\App\User')->getFields());
        $this->assertCount(0, $this->parsed->getModel('\App\User')->getCommands());

        $fieldNames = collect($this->parsed->getModel('User')->getFields())->map->getName()->all();
        $this->assertEquals(['id', 'name', 'other_name', 'phone_id', 'other_phone_id'], $fieldNames);

        $this->assertCount(1, $this->parsed->getModel('\App\Phone')->getMethods());
        $this->assertCount(2, $this->parsed->getModel('\App\Phone')->getFields());
        $this->assertCount(1, $this->parsed->getModel('\App\Phone')->getCommands());
    }

    /** @test */
    public function resolve_has_one_via_field()
    {
        $this->parse('
            namespace App\\Models\\Test3
            User
                phone()
                
            Phone
                user_id: integer
                user()
        ');

        $this->assertTrue($this->findMethod('Phone', 'user')->isBelongsTo());
        $this->assertTrue($this->findMethod('User', 'phone')->isHasOne());
    }

    /** @test */
    public function resolve_has_one_via_join()
    {
        $this->parse('
            namespace App\\Models\\Test3
            User
                phone()
                
            Phone
                user(): User Join(Phone.user_id = User.id)
        ');

        $this->assertTrue($this->findMethod('User', 'phone')->isHasOne());
        $this->assertTrue($this->findMethod('Phone', 'user')->isBelongsTo());
    }

    /** @test */
    public function parses_has_one_2nd()
    {
        $this->parse('
            User
                phone(): Phone  Join(User.phone_id = Phone.id)
                
            Phone
                user()
        ');

        $this->assertEquals('phone', $this->findMethod('\App\User', 'phone')->getName());
        $this->assertEquals('User.phone_id = Phone.id', $this->findMethod('\App\User', 'phone')->getJoinString());
    }

    /** @test */
    public function parses_namespaces()
    {
        $this->parse('
            namespace \App\Models app/Models
            User1
            User2

            namespace \App\Other\ app/Other/
            User3
            User4
            
            \OtherNs\Model
                
            namespace App\Waaa
        ');

        $this->assertEquals('app/Models/', $this->parsed->getPathForNamespace('\App\Models'));
        $this->assertEquals('app/Other/', $this->parsed->getPathForNamespace('App\Other'));
        $this->assertEquals('app/Waaa/', $this->parsed->getPathForNamespace('App\Waaa'));

        $this->assertNotNull($this->parsed->getModel('\App\Models\User1'));
        $this->assertNotNull($this->parsed->getModel('\App\Models\User2'));
        $this->assertNotNull($this->parsed->getModel('\App\Other\User3'));
        $this->assertNotNull($this->parsed->getModel('\App\Other\User4'));
        $this->assertNotNull($this->parsed->getModel('\OtherNs\Model'));

        $this->assertSame($this->parsed, $this->parsed->getModel('\OtherNs\Model')->getSchema());
        $this->assertEquals('Model', $this->parsed->getModel('\OtherNs\Model')->getShortName());
    }

    /** @test */
    public function parse_at_zero_indent()
    {
        $this->parse("Item\n"."  title: string\n");
        $this->assertTrue(true); // no exception happened
    }

    /** @test */
    public function parses_command()
    {
        $c = (new Parser())->parseCommand('RENAME INDEX idx3 TO idx4');
        $this->assertEquals('Command', $c->getCommandType());
        $this->assertEquals('RenameIndex', $c->getSubType());
        $this->assertEquals('idx3', $c->getArg1());
        $this->assertEquals('idx4', $c->getArg2());
    }

    /** @test */
    public function parses_method()
    {
        $parser = new Parser();
        $parser->parse('Model'); // sets the model
        $m = $parser->parseMethod('
            tags() via Taggable.taggable(): Tag[] <- Tag.videos(), 
                        Join(Table1.field1 = Table2.field2 AND Table2.field3 = Table3.field4),  
                        As("subscription"), 
                        PivotWithTimestamps
            ');

        $this->assertEquals('tags', $m->getName());
        $this->assertEquals('Taggable.taggable()', $m->getVia());
        $this->assertEquals('Tag[]', $m->getReturnType());
        $this->assertEquals('Tag.videos()', $m->getInverseOf());
        $this->assertEquals('Table1.field1 = Table2.field2 AND Table2.field3 = Table3.field4', $m->getJoinString());
        $this->assertEquals('subscription', $m->getAs());
        $this->assertTrue($m->isPivotWithTimestamps());
    }

    /** @test */
    public function parses_field()
    {
        $parser = new Parser();
        $parser->parse('Model'); // sets the model
        $m = $parser->parseField('id_field: big_integer PrimaryKey, Index, Unique, Index(idxi), Unique(idxu), NotNull');

        $this->assertEquals('id_field', $m->getName());
        $this->assertEquals('big_integer', $m->getFieldType());
        $this->assertEquals(false, $m->isNullable());
        $this->assertTrue($m->isPrimaryKey());
        $this->assertCount(2, $m->getUniqueIndexNames());
        $this->assertCount(2, $m->getIndexNames());
        $this->assertEquals(['models_id_field_idx', 'idxi'], $m->getIndexNames());
        $this->assertEquals(['models_id_field_unique_idx', 'idxu'], $m->getUniqueIndexNames());
    }

    /** @test */
    public function get_tables()
    {
        $this->parse('
            User
                name: string
            Phone
        ');

        $this->assertCount(
            2,
            $this->parsed->getTables()
        ); // it might have pivot tables to create! but it needs to see if pivot is explicitly defined
        $this->assertEquals(['users', 'phones'], $this->parsed->getTables());
        $this->assertCount(2, $this->parsed->getModel('\App\User')->getFields());
    }

    /** @test */
    public function get_tables_pivots()
    {
        $this->parse('
            User
                roles()
            Role
                users()
        ');

        // it might have pivot tables to create! but it needs to see if pivot is explicitly defined
        $this->assertCount(3, $this->parsed->getTables());
        $this->assertEquals(['users', 'roles', 'role_user'], $this->parsed->getTables());
    }

    /** @test */
    public function get_tables_pivots_via_join()
    {
        $this->parse('
            User
                roles(): Role[]  Join(User.id = role_user_custom.user_id AND role_user_custom.role_id = roles.id)
                
            Role
                users()
        ');
        // the third table `roles` seems to exist, but we know nothing about it

        $this->assertCount(3, $this->parsed->getTables());
        $this->assertEquals(['users', 'roles', 'role_user_custom'], $this->parsed->getTables());
    }

    /** @test */
    public function bad_join()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Joins with "AND" are currently used only for');

        $parser = new Parser();
        $parser->parse('Model');
        $parser->parseMethod('
            roles(): Role[]  Join(User.id = role_user_custom_other.user_id AND role_user_custom.role_id = roles.id)
        ');
    }

    /** @test */
    public function get_tables_pivots_via_join_table_name()
    {
        $this->parse('
            User
                roles(): Role[]  Join(users.id = role_user_custom.user_id AND role_user_custom.role_id = roles.id)
            
            Role
                users()
        ');

        $this->assertEquals('Role[]', $this->findMethod('User', 'roles')->getReturnType());
        $this->assertTrue($this->findMethod('User', 'roles')->isManyToMany());
        $this->assertCount(3, $this->parsed->getTables());
        $this->assertEquals(['users', 'roles', 'role_user_custom'], $this->parsed->getTables());
    }

    /** @test */
    public function returns_many()
    {
        $this->parse('
            User
                role(): Role[]
        ');

        $this->assertEquals('Role[]', $this->findMethod('User', 'role')->getReturnType());
        $this->assertTrue($this->findMethod('User', 'role')->returnsMany());

        $this->parsed = (new Parser())->parse('
            User
                role() via role_id: Role
        ');

        $this->assertFalse($this->findMethod('User', 'role')->returnsMany());
    }

    /** @test */
    public function has_one_has_no_pivots()
    {
        $this->parse('
            User
                roles()
            Role
                user()
        ');

        $this->assertCount(2, $this->parsed->getTables());
        $this->assertEquals(['users', 'roles'], $this->parsed->getTables());
    }

    /** @test */
    public function should_not_use_plurals_in_model()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You are using plural "Users" as model name');

        $this->parse('Users');
    }

    /** @test */
    public function spec_1()
    {
        $this->parse('
            # ------------            
            # One To One
            
            namespace \App\Models
            
            User
                phone() via phone_id
                
            Phone
                user()
            
        ');

        $this->assertModelExists('User');
        $this->assertModelExists('Phone');
        $this->assertModelExists('\App\Models\User');
        $this->assertMethodExists('User', 'phone');
        $this->assertMethodExists('Phone', 'user');
        $this->assertFieldExists('User', 'phone_id');

        $this->assertFalse($this->findMethod('User', 'phone')->returnsMany());
        $this->assertFalse($this->findMethod('Phone', 'user')->returnsMany());

        $phoneMethod = $this->findMethod('User', 'phone');
        $this->assertEquals(MethodCommand::BELONGS_TO, $phoneMethod->relationType());

        $phoneMethod = $this->findMethod('Phone', 'user');
        $this->assertEquals(MethodCommand::HAS_ONE, $phoneMethod->relationType());
    }

    /** @test */
    public function which_has_one()
    {
        $message = 'Model User contains a confusing One to One definition between `User.phone()` and `Phone.user()`. '.
            'One to one requires a field in one of these tables. To resolve it: '.
            'if User (usually) belongs to Phone - then add `phone() via phone_id` to User; '.
            'otherwise if Phone (usually) belongs to User - then add `user() via user_id` to Phone.';
        $this->expectExceptionMessage($message);

        $this->parse('
            # --------------------            
            # Confusing One To One (which one has the field: User has phone_id or Phone has user_id?)
            
            namespace \App\Models
            
            User
                phone()
                
            Phone
                user()
            
        ');

        $phoneMethod = $this->findMethod('User', 'phone');
        $phoneMethod->relationType();
    }

    /** @test */
    public function parses_fields()
    {
        $this->parse('
            namespace App\\Models\\Test2
            
            User
                phone()

            Phone
                number: string
                user() via user_id
        ');

        $this->assertFieldExists('Phone', 'number');
        $this->assertMethodNotExists('Phone', 'number');
    }

    /** @test */
    public function parses_via()
    {
        $p = new Parser();
        $p->parse('Model');
        $m = $p->parseMethod('phone() via phone_id');

        $this->assertEquals('phone_id', $m->getVia());
    }

    /** @test */
    public function spec_2()
    {
        $this->parse('
            User
                phone(): Phone  Join(User.phone_id = Phone.id)
                
            Phone
                user()
            
        ');

        $this->assertModelExists('User');
        $this->assertModelExists('Phone');
        $this->assertModelExists('User');
        $this->assertMethodExists('User', 'phone');
        $this->assertMethodExists('Phone', 'user');
        $this->assertFieldExists('User', 'phone_id');

        $this->assertFalse($this->findMethod('User', 'phone')->returnsMany());
        $this->assertFalse($this->findMethod('Phone', 'user')->returnsMany());

        $phoneMethod = $this->findMethod('User', 'phone');
        $this->assertEquals(MethodCommand::BELONGS_TO, $phoneMethod->relationType());

        $phoneMethod = $this->findMethod('Phone', 'user');
        $this->assertEquals(MethodCommand::HAS_ONE, $phoneMethod->relationType());
    }

    /** @test */
    public function spec_3()
    {
        $this->parse('
            User
                # if you need specific type
                home_phone_id: integer         
                # TODO: how to do: withDefault(["name" => ...])
                home_phone(): Phone  Join(Phone.id_field = User.home_phone_id)     
            
            Phone
                id_field: big_integer  PrimaryKey, Index, Unique, Unique(idx2)
                other_field: string  Unique(idx2)
                user(): User  Join(Phone.id_field = User.home_phone_id)
                new_name: string
            
        ');

        $this->assertFieldExists('User', 'home_phone_id');

        $phoneMethod = $this->findMethod('Phone', 'user');
        $this->assertEquals(MethodCommand::BELONGS_TO, $phoneMethod->relationType());
    }

    /** @test */
    public function spec_3_inverse_method()
    {
        $this->parse('
            User
                home_phone(): Phone  Join(Phone.id_field = User.home_phone_id)     
            
            Phone
                user(): User
            
        ');

        $this->assertEquals('home_phone', $this->findMethod('Phone', 'user')->inverseMethod()->getName());
    }

    /** @test */
    public function spec_3_double_belongs_to()
    {
        $this->parse('
            User
                home_phone(): Phone  Join(Phone.id_field = User.home_phone_id)     
            
            Phone
                user(): User  Join(Phone.id_field = User.home_phone_id)
            
        ');

        $phoneMethod = $this->findMethod('User', 'home_phone');
        $this->assertEquals(MethodCommand::BELONGS_TO, $phoneMethod->relationType());

        $phoneMethod = $this->findMethod('Phone', 'user');
        $this->assertEquals(MethodCommand::BELONGS_TO, $phoneMethod->relationType());
    }

    /** @test */
    public function spec_3_sub1()
    {
        $this->parse('
            User
                home_phone(): Phone  Join(Phone.id_field = User.home_phone_id)     
            
            Phone
                user(): User          
        ');

        $this->assertTrue($this->findMethod('Phone', 'user')->returnsOne());
        $this->assertEquals('id_field', $this->findMethod('User', 'home_phone')->fieldThatJoinCreatesIn('Phone'));

        // because other join creates
        $this->assertEquals('id_field', $this->findMethod('Phone', 'user')->belongsToFieldName());

        $this->assertEquals(MethodCommand::BELONGS_TO, $this->findMethod('Phone', 'user')->relationType());
        $this->assertEquals(MethodCommand::BELONGS_TO, $this->findMethod('User', 'home_phone')->relationType());
    }

    /** @test */
    public function spec_3_sub4()
    {
        $this->expectExceptionMessage(
            'Method `Phone.user()` returns only one thing, so it is probably of type `Belongs To`, which requires field `user_id`'
        );

        $this->parse('
            Phone
                user(): User          
        ');

        $this->findMethod('Phone', 'user')->relationType();
    }

    /** @test */
    public function spec_3_sub3()
    {
        $this->parse('
            User
                home_phone(): Phone  Join(Phone.id_field = User.home_phone_id)     
            
            Phone
                user(): User          
        ');

        // join in User creates id_field in Phone.user, so it is (double) "belongs to"

        $this->assertTrue($this->findMethod('Phone', 'user')->returnsOne());
        $this->assertEquals('id_field', $this->findMethod('User', 'home_phone')->fieldThatJoinCreatesIn('Phone'));

        // because other join creates
        $this->assertEquals('id_field', $this->findMethod('Phone', 'user')->belongsToFieldName());

        $this->assertEquals(MethodCommand::BELONGS_TO, $this->findMethod('Phone', 'user')->relationType());
    }

    /** @test */
    public function spec_3_sub2()
    {
        $this->parse('
            User
                home_phone(): Phone  Join(Phone.id_field = User.home_phone_id)     
            
            Phone
                user_id: integer
                user(): User            
        ');

        $phoneMethod = $this->findMethod('Phone', 'user');
        $this->assertEquals(MethodCommand::BELONGS_TO, $phoneMethod->relationType());
    }

    /** @test */
    public function spec_4()
    {
        $this->parse('
            # ------------
            # One To Many
            # This will create post_id field (with ref.integ?)

            Post
                comments()

            Comment
                post()

        ');

        $this->assertTrue($this->findMethod('Post', 'comments')->isHasMany());
        $this->assertTrue($this->findMethod('Comment', 'post')->isBelongsTo());
        // Post has many Comments, Comment belongs to Post
        $this->assertEquals('post_id', $this->findMethod('Comment', 'post')->belongsToFieldName());
    }

    /** @test */
    public function spec_5()
    {
        $this->parse('
            # ------------
            # Many To Many
            # this will create role_user table with role_id, user_id columns

            User
                roles()

            Role
                users()

        ');

        $this->assertEquals(['users', 'roles', 'role_user'], $this->parsed->getTables());
        $this->assertTrue($this->findMethod('User', 'roles')->isBelongsToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isBelongsToMany());
        $this->assertTrue($this->findMethod('User', 'roles')->isManyToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isManyToMany());
    }

    /** @test */
    public function spec_6()
    {
        $this->parse('
            User
                roles_1(): Role[]  Join(User.id = role_user_custom.user_id AND role_user_custom.role_id = Role.id)
                # this will create a table role_user_custom

            Role
                users(): User[] <- User.roles_1()

        ');

        $this->assertEquals('User.roles_1()', $this->findMethod('Role', 'users')->getInverseOf());
        $this->assertEquals('roles_1', $this->findMethod('Role', 'users')->inverseMethod()->getName());
        $this->assertEquals(['users', 'roles', 'role_user_custom'], $this->parsed->getTables());
        $this->assertTrue($this->findMethod('User', 'roles_1')->isBelongsToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isBelongsToMany());
        $this->assertTrue($this->findMethod('User', 'roles_1')->isManyToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isManyToMany());
    }

    /** @test */
    public function spec_6a()
    {
        $this->parse('
            User
                roles_1(): Role[]  Join(User.id = role_user_custom.user_id AND role_user_custom.role_id = Role.id)
                # this will create a table role_user_custom

            Role
                users(): User[]

        ');

        $this->assertEquals('roles_1', $this->findMethod('Role', 'users')->inverseMethod()->getName());
        $this->assertEquals(['users', 'roles', 'role_user_custom'], $this->parsed->getTables());
        $this->assertTrue($this->findMethod('User', 'roles_1')->isBelongsToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isBelongsToMany());
        $this->assertTrue($this->findMethod('User', 'roles_1')->isManyToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isManyToMany());
    }

    /** @test */
    public function spec_7a()
    {
        $this->parse('
            RoleUserCustom (role_user_c)
        ');

        $this->assertEquals('role_user_c', $this->parsed->getModel('\App\RoleUserCustom')->getTableName());

        $this->parse('
            RoleUserCustom
        ');

        $this->assertEquals('role_user_customs', $this->parsed->getModel('\App\RoleUserCustom')->getTableName());
    }

    /** @test */
    public function spec_7()
    {
        $this->parse('
            # -- or with model:

            RoleUserCustom (role_user_custom)

            User
                roles(): Role[]  Join(User.id = RoleUserCustom.user_id AND RoleUserCustom.role_id = Role.id), 
                        As("subscription"), PivotWithTimestamps

            Role
                users(): User[] <- User.roles()
        ');

        $this->assertEquals(['role_user_custom', 'users', 'roles'], $this->parsed->getTables());
        $this->assertTrue($this->findMethod('User', 'roles')->isBelongsToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isBelongsToMany());
        $this->assertTrue($this->findMethod('User', 'roles')->isManyToMany());
        $this->assertTrue($this->findMethod('Role', 'users')->isManyToMany());
    }

    /** @test */
    public function has_many_through()
    {
        $this->assertNull(FieldCommand::fromString('posts() via User', new ModelCommand('', '')));
    }

    /** @test */
    public function spec_8()
    {
        $this->parse('
            # Has Many Through
            # (tables withoutPrimaryKey)

            Country
                name: string
                posts() via User

            User
                country() via country_id
                posts()

            Post
                user()
                title: string
                country() via User
                #country() via User: Country
                #country() via User.country(): Country  Join(Post.user_id = User.id AND User.country_id = Country.id)

        ');

        // Country posts isHasManyThrough (Post -> User)
        $this->assertTrue($this->findMethod('User', 'country')->isBelongsTo());
        $this->assertEquals(['id', 'name'], $this->findModel('Country')->getFieldsNames());

        $this->assertEquals('User.country()', $this->findMethod('Country', 'posts')->inverseMethod()->humanName());

        // Country.posts(): Post[] --> User.country(): Country ->

        $this->assertTrue($this->findMethod('Country', 'posts')->isHasManyThrough());

        $m = $this->findMethod('Country', 'posts')->hasManyThroughIntermediateMethod();
        $this->assertEquals('posts', $m->getName());
        $this->assertEquals('User', $m->getModel()->getShortName());

        $this->assertEquals('Post[]', $this->findMethod('Country', 'posts')->getReturnType());
        $this->assertEquals('post_id', $this->findMethod('User', 'posts')->belongsToFieldName());
    }

    /** @test */
    public function spec_8b()
    {
        $this->parse('
            # Has Many Through
            # (tables withoutPrimaryKey)

            Country
                name: string
                posts() via User

            User
                users_country() via country_id: Country
                posts()

            Post
                user()
                title: string
                country() via User.users1_country()
                #country() via User: Country
                #country() via User.country(): Country  Join(Post.user_id = User.id AND User.country_id = Country.id)

        ');

        $this->assertTrue($this->findMethod('User', 'users_country')->isBelongsTo());

        $this->assertTrue($this->findMethod('Country', 'posts')->isHasManyThrough());

        $m = $this->findMethod('Country', 'posts')->inverseMethod();
        $this->assertEquals('users_country', $m->getName()); // $this->hasManyThrough('App\Post', 'App\User');
        $this->assertEquals('User', $m->getModel()->getShortName());

        $this->assertEquals('Post[]', $this->findMethod('Country', 'posts')->getReturnType());
    }

    /** @test */
    public function spec_8a()
    {
        $this->expectExceptionMessage('define method `Country.users()`');
        $this->parse('
            Country
                name: string

            User
                country()
        ');

        $this->assertTrue($this->findMethod('User', 'country')->isBelongsTo());
    }

    /** @test */
    public function spec_9a()
    {
        $this->expectExceptionMessage('Your `Comment.commentable()` method asks for polymorphic relation with array, I am not sure how to do it, did you mean just `Post|Video` instead of `Post|Video[]`?');

        $this->parse('
            Comment
                commentable(): Post|Video[]
        ');

        $this->assertEquals('Post|Video[]', $this->findMethod('Comment', 'commentable')->getReturnType());
    }

    /** @test */
    public function spec_9a2()
    {
        $this->parse('
            Comment
                # plural name, but single (polymorphic) return type, should obey return type
                commentables(): Post|Video
        ');

        $this->assertEquals('Post|Video', $this->findMethod('Comment', 'commentables')->getReturnType());
    }

    /** @test */
    public function spec_9a1()
    {
        $this->parse('
            Comment
                commentable(): Post|Video
        ');

        $this->assertEquals('Post|Video', $this->findMethod('Comment', 'commentable')->getReturnType());
    }

    /** @test */
    public function spec_9()
    {
        $this->parse('
            # Polymorphic Relations

            Post
                title: string
                body: text
                comments()

            Video
                title: string
                comments()

            Comment
                body: text
                commentable(): Post|Video

        ');

        $this->assertTrue($this->findMethod('Comment', 'commentable')->isPolymorphic()); // ->morphTo();

        $this->assertEquals(
            'Comment.commentable()',
            $this->findMethod('Post', 'comments')->inverseMethod()->humanName()
        );
        $this->assertTrue($this->findMethod(
            'Post',
            'comments'
        )->isMorphMany()); // ->morphMany("App\Comment", "commentable");
        $this->assertTrue($this->findMethod(
            'Video',
            'comments'
        )->isMorphMany()); // ->morphMany("App\Comment", "commentable");

        $this->assertEquals(['id', 'body', 'commentable_id', 'commentable_type'], $this->fieldNamesFor('Comment'));
    }

    /** @test */
    public function spec_10()
    {
        $this->parse('
            # ----------------------------------
            # Many To Many Polymorphic Relations
            # tag is `morphedByMany`

            Post
                tags() via Taggable

            Video
                tags() via Taggable

            Tag
                posts() via Taggable
                videos() via Taggable

            Taggable
                tag() via tag_id
                taggable(): Post|Video
        ');

        $this->assertTrue($this->findMethod('Taggable', 'taggable')->isPolymorphic());
        $this->assertTrue($this->findMethod('Taggable', 'tag')->isBelongsTo());

        $this->assertEquals('Taggable.taggable()', $this->findMethod('Post', 'tags')->inverseMethod()->humanName());
        $this->assertEquals('Taggable.taggable()', $this->findMethod('Video', 'tags')->inverseMethod()->humanName());
        $this->assertEquals('Taggable.tag()', $this->findMethod('Tag', 'posts')->inverseMethod()->humanName());
        $this->assertEquals('Taggable.tag()', $this->findMethod('Tag', 'videos')->inverseMethod()->humanName());

        $this->assertTrue($this->findMethod('Post', 'tags')->isMorphToMany()); // morphToMany('App\Tag', 'taggable')
        $this->assertTrue($this->findMethod('Video', 'tags')->isMorphToMany()); // morphToMany('App\Tag', 'taggable')

        $this->assertEquals('Taggable.tag()', $this->findMethod('Tag', 'posts')->inverseMethod()->humanName());

        // morphedByMany('App\Post', 'taggable')
        $this->assertTrue($this->findMethod('Tag', 'posts')->isMorphedByMany());
        // morphedByMany('App\Video', 'taggable')
        $this->assertTrue($this->findMethod('Tag', 'videos')->isMorphedByMany());
        // morphedByMany('App\Video', 'taggable')
        $this->assertTrue($this->findMethod('Tag', 'videos')->isMorphedByMany());

        $this->assertEquals(['id', 'tag_id', 'taggable_id', 'taggable_type'], $this->fieldNamesFor('Taggable'));
        $this->assertEquals('integer', $this->findField('Taggable', 'taggable_id')->getFieldType());
        $this->assertEquals('string', $this->findField('Taggable', 'taggable_type')->getFieldType());
    }

    /** @test */
    public function spec_11()
    {
        $this->parse('
            # ------------------------------
            # Table names, primary keys, guarded, not null

            Car (car_table)
                weird_id: integer PrimaryKey
                complex_id: integer PrimaryKey
                model: string  NotNull, Guarded
                make: text
                owner(): Owner  NotNull

            Owner
                name: text
                cars(): Car[]

        ');

        $this->assertEquals('car_table', $this->findModel('Car')->getTableName());
        $this->assertEquals('integer', $this->findField('Car', 'weird_id')->getFieldType());
        $this->assertTrue($this->findField('Car', 'weird_id')->isPrimaryKey());
        $this->assertTrue($this->findField('Car', 'complex_id')->isPrimaryKey());
        $this->assertSame(false, $this->findField('Car', 'model')->isNullable());
        $this->assertTrue($this->findField('Car', 'model')->isGuarded());
        // null in isNullable means "not specified"
        $this->assertSame(null, $this->findField('Car', 'make')->isNullable());
        $this->assertTrue($this->findMethod('Car', 'owner')->isBelongsTo());
        $this->assertTrue($this->findMethod('Owner', 'cars')->isHasMany());
    }

    /** @test */
    public function spec_12()
    {
        $this->parse('
            # ------------
            # Self-reference, specific field

            Human
                boss() via guys_boss_id: Human  <- Human.employees(), NotNull
                employees(): Human[]

                RENAME FIELD field_old_name TO field_new_name
                DELETE FIELD field_name_1

                RENAME INDEX idx3 TO idx4
                DELETE INDEX idx_old
                DELETE INDEX slug_idx

        ');

        $this->assertTrue($this->findMethod('Human', 'boss')->viaCreatesField());
        $this->assertEquals('Human.employees()', $this->findMethod('Human', 'boss')->inverseMethod()->humanName());
    }

    /** @test */
    public function spec_12a()
    {
        $this->parse('
            Human
                boss() via guys_boss_id: Human <- Human.employees()
                employees(): Human[] <- Human.boss()

        ');

        $this->assertTrue($this->findMethod('Human', 'boss')->viaCreatesField());
        $this->assertContains('guys_boss_id', $this->fieldNamesFor('Human'));

        $this->assertEquals('Human.employees()', $this->findMethod('Human', 'boss')->getInverseOf());
        $this->assertEquals('Human.employees()', $this->findMethod('Human', 'boss')->inverseMethod()->humanName());
        $this->assertEquals('Human.boss()', $this->findMethod('Human', 'employees')->inverseMethod()->humanName());
    }

    /** @test */
    public function spec_12c()
    {
        $this->parse('
            Human
                boss(): Human <- Human.employees()
                employees(): Human[] <- Human.boss()

        ');

        $this->assertContains('boss_id', $this->fieldNamesFor('Human'));

        $this->assertEquals('Human.employees()', $this->findMethod('Human', 'boss')->getInverseOf());
        $this->assertEquals('Human.employees()', $this->findMethod('Human', 'boss')->inverseMethod()->humanName());
        $this->assertEquals('Human.boss()', $this->findMethod('Human', 'employees')->inverseMethod()->humanName());
    }

    /** @test */
    public function spec_12b()
    {
        $this->expectExceptionMessage('parse');

        $this->parse('
            Human
                # error, needs colon (:)
                boss() via guys_boss_id  <- Human.employees(), NotNull
                employees(): Human[]
        ');
    }

    /** @test */
    public function spec_13()
    {
        $this->parse('');
        // assert null, unguarded
        $this->assertTrue($this->parsed->getDefaultIsNullable());
        $this->assertFalse($this->parsed->getDefaultIsGuarded());

        $this->parse('
            default not null, unguarded
        ');
        $this->assertFalse($this->parsed->getDefaultIsNullable());
        $this->assertFalse($this->parsed->getDefaultIsGuarded());

        $this->parse('
            default null, guarded
        ');
        $this->assertTrue($this->parsed->getDefaultIsNullable());
        $this->assertTrue($this->parsed->getDefaultIsGuarded());
    }

    /** @test */
    public function referential_integrity()
    {
        /* @see https://laravel.com/docs/5.6/migrations#foreign-key-constraints */
        $this->markTestIncomplete('TODO@slava: create test referential_integrity');
    }

    /** @test */
    public function weird_primary_key()
    {
        $this->parse('
            User
                user_primary_key: bigIncrements PrimaryKey
                phone()
                
            Phone
                phone_primary_key: bigIncrements PrimaryKey
                user() via weird_user_id
        ');

        $this->assertEquals(
            'bigIncrements',
            $this->parsed->getModel('User')->getField('user_primary_key')->getFieldType()
        );
    }

    /** @test */
    public function implicit_primary_key()
    {
        $this->parse('
            User
                name: string
                email: string
                number: integer
        ');

        $this->assertEquals(['id'], $this->parsed->getModel('User')->getPrimaryKeyFieldNames());
    }

    /** @test */
    public function join_m2m()
    {
        $this->parse('
            User
                user_pk: increments
                name: string
                roles(): Role[] Join(Role.role_pk = assigned_roles.role_pk1 AND assigned_roles.user_pk1 = User.user_pk)
                
            Role
                role_pk: increments
                name: string
                users(): User[] Join(Role.role_pk = assigned_roles.role_pk1 AND assigned_roles.user_pk1 = User.user_pk) 
        ');

        $this->assertEquals('role_pk1', $this->findMethod('Role', 'users')->ourKeyInPivot());
    }

    /** @test */
    public function join_m2m2()
    {
        $this->parse('
            AssignedRole
            
            User
                user_pk: integer
                name: string
                roles(): Role[] Join(Role.role_pk = AssignedRole.role_pk1 AND AssignedRole.user_pk1 = User.user_pk)
                
            Role
                role_pk: integer
                name: string
                users(): User[] 
        ');

        $this->assertEquals(
            'AssignedRole',
            $this->findMethod('User', 'roles')->explicitManyToManyModel()->getShortName()
        );
        $this->assertEquals(
            'AssignedRole',
            $this->findMethod('Role', 'users')->explicitManyToManyModel()->getShortName()
        );
        $this->assertEquals([], $this->findModel('User')->getImplicitPivotTables()); // since it's explicit
        $this->assertEquals([], $this->findModel('Role')->getImplicitPivotTables());
        $this->assertTrue($this->findModel('AssignedRole')->extendsPivot());
    }

    /** @test */
    public function should_not_be_two_tables_with_same_table_name()
    {
        $this->expectExceptionMessage('There are two models with table_name is `assigned`: AssignedRole and AssignedUser. You must have exactly 1 Model for 1 Table.');

        $this->parse('
            AssignedRole (assigned)
            AssignedUser (assigned)
        ');
    }

    /** @test */
    public function numbered_types()
    {
        $this->parse('
            Type
                c: char(100)
                d: decimal(8)
                d1: decimal(8, 2)
                f: float(8,2)
                s: string(100)
                ud: unsignedDecimal(8, 2)
                en: enum(["easy", \'hard\'])
        ');

        $this->assertEquals('char', $this->findField('Type', 'c')->getFieldType());
        $this->assertEquals(100, $this->findField('Type', 'c')->getParam1());
        $this->assertEquals(8, $this->findField('Type', 'd1')->getParam1());
        $this->assertEquals(2, $this->findField('Type', 'd1')->getParam2());
        $this->assertEquals("[\"easy\", 'hard']", $this->findField('Type', 'en')->getParam1());
    }

    private function assertModelExists($model)
    {
        $this->assertInstanceOf(ModelCommand::class, $this->parsed->getModel($model));
    }

    private function assertMethodExists($model, $method)
    {
        $this->assertInstanceOf(MethodCommand::class, $this->parsed->getModel($model)->getMethod($method));
    }

    private function assertMethodNotExists($model, $method)
    {
        try {
            $this->assertNotInstanceOf(MethodCommand::class, $this->parsed->getModel($model)->getMethod($method));
        } catch (MethodNotFound $e) {
            return;
        }
        $this->fail("Method $model.$method() exists, but should not");
    }

    private function assertFieldExists($model, $field)
    {
        $this->assertInstanceOf(FieldCommand::class, $this->findField($model, $field));
    }

    private function parse($string)
    {
        return $this->parsed = (new Parser())->parse($string);
    }

    private function findMethod($model, $method)
    {
        return $this->parsed->getModel($model)->getMethod($method);
    }

    private function fieldNamesFor($model)
    {
        return collect($this->parsed->getModel($model)->getFields())->map->getName()->all();
    }

    private function findField($model, $field): FieldCommand
    {
        return $this->parsed->getModel($model)->getField($field);
    }

    private function findModel($model)
    {
        return $this->parsed->getModel($model);
    }
}
