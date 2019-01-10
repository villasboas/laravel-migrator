<?php

namespace Migrator\Tests;

use Migrator\Schema\FieldCommand;
use Migrator\Schema\Migrator\MergeModelFiles;
use Migrator\Schema\Migrator\ModelBuilder;
use Migrator\Schema\ModelCommand;
use Migrator\Schema\Parser;
use Migrator\Schema\Schema;

class MergeFileTest extends BaseTestCase
{
    use GenerateAndRun;

    /** @test */
    public function merges_namespacestmt()
    {
        $this->markTestIncomplete('TODO@slava: create test ');
    }

    /** @test */
    public function merges_use1()
    {
        $this->markTestIncomplete('TODO@slava: create test ');
    }

    /** @test */
    public function merges_extendsstmt()
    {
        $this->markTestIncomplete('TODO@slava: create test ');
    }

    /** @test */
    public function merges_guarded()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                is_admin: boolean Guarded
        ');

        $this->generateAndRun('
            namespace {namespace}
            
            User
                is_admin: boolean Guarded
                is_admin2: boolean Guarded
        ');

        $this->assertModelsContain("protected \$guarded = ['is_admin', 'is_admin2'];");
    }

    /** @test */
    public function merges_guarded1()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                is_admin: boolean
        ');

        $this->generateAndRun('
            namespace {namespace}
            
            User
                is_admin: boolean Guarded
        ');

        $this->assertModelsContain("protected \$guarded = ['is_admin'];");
    }

    /** @test */
    public function merges_primarykey()
    {
        $this->markTestIncomplete('TODO@slava: create test ');
    }

    /** @test */
    public function merges_casts()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                name: string
        ');

        $this->generateAndRun('
            namespace {namespace}

            User
                name: string
                js1: json 
        ');

        $this->generateAndRun('
            namespace {namespace}

            User
                name: string
                js1: json 
                js2: json 
        ');

        $this->assertContains('$casts', $this->getModelContents('User'));
        $this->assertContains('js1', $this->getModelContents('User'));
        $this->assertContains('js2', $this->getModelContents('User'));

        $user = $this->newInstanceOf('User')->create(['js1' => [1], 'js2' => [2]]);
        $this->assertEquals([1], $this->newInstanceOf('User')->first()->js1);
        $this->assertEquals([2], $this->newInstanceOf('User')->first()->js2);
    }

    /** @test */
    public function merges_dates()
    {
        $this->markTestIncomplete('TODO@slava: create test ');
    }

    /** @test */
    public function should_add_guarded_if_it_was_removed()
    {
        $file = '<?php namespace App;
use Illuminate\Database\Eloquent\Model;
class GeoRect extends Model
{
}';
        $tmp = tempnam('/tmp', 'model');
        file_put_contents($tmp, $file);
        $schema = new Schema();
        $model = ModelCommand::fromString('GeoRect', 'App');
        $field = FieldCommand::fromString('history: json Guarded', $model);
        $field->setSchema($schema);
        $model->addField($field);
        (new MergeModelFiles($tmp, new ModelBuilder($tmp, 'GeoRect', $model)))->merge();
        $newContent = file_get_contents($tmp);
        $this->assertContains('$guarded', $newContent);
        $this->assertNotContains("\n\n\n", $newContent);

        // should not change
        (new MergeModelFiles($tmp, new ModelBuilder($tmp, 'GeoRect', $model)))->merge();
        $newContent2 = file_get_contents($tmp);
        $this->assertEquals($newContent, $newContent2);
    }

    /** @test */
    public function should_add_casts_if_it_was_removed()
    {
        $file = '<?php namespace App;
use Illuminate\Database\Eloquent\Model;
class GeoRect extends Model
{
}';
        $tmp = tempnam('/tmp', 'model');
        file_put_contents($tmp, $file);
        $schema = new Schema();
        $model = ModelCommand::fromString('GeoRect', 'App');
        $field = FieldCommand::fromString('history: json', $model);
        $field->setSchema($schema);
        $model->addField($field);
        (new MergeModelFiles($tmp, new ModelBuilder($tmp, 'GeoRect', $model)))->merge();
        $newContent = file_get_contents($tmp);
        $this->assertContains('$casts', $newContent);
        $this->assertNotContains("\n\n\n", $newContent);
        $this->assertNotContains("{\n\n", $newContent);

        // should not change
        (new MergeModelFiles($tmp, new ModelBuilder($tmp, 'GeoRect', $model)))->merge();
        $newContent2 = file_get_contents($tmp);
        $this->assertEquals($newContent, $newContent2);
    }

    /** @test */
    public function should_add_methods()
    {
        $file = '<?php namespace App;
use Illuminate\Database\Eloquent\Model;
class GeoRect extends Model
{
}';
        $tmp = tempnam('/tmp', 'model');
        file_put_contents($tmp, $file);
        $schema = new Schema();

        $p = new Parser();
        $schema = $p->parse('
        GeoRect
            histories()
        History
            geo_rect()
        ');

        $model = $schema->getModel('GeoRect');

        (new MergeModelFiles($tmp, new ModelBuilder($tmp, 'GeoRect', $model)))->merge();
        $newContent = file_get_contents($tmp);
        $this->assertNotContains("\n\n\n", $newContent);
        $this->assertNotContains("{\n\n", $newContent);
        $this->assertContains('public function histories()', $newContent);

        // should not change
        (new MergeModelFiles($tmp, new ModelBuilder($tmp, 'GeoRect', $model)))->merge();
        $newContent2 = file_get_contents($tmp);
        $this->assertEquals($newContent, $newContent2);
    }

    /** @test */
    public function merges_datetime()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            Item
        ');

        $ns = $this->generateAndRun('
            namespace {namespace}
            Item
                last_bought: datetime
        ');

        $this->assertContains('last_bought', $this->getModelContents('Item'));
        $this->assertContains("protected \$dates = [\n        'last_bought',\n    ];\n",
            $this->getModelContents('Item'));

        $ns = $this->generateAndRun('
            namespace {namespace}
            Item
                last_bought: datetime
                last_sold: datetime
        ');

        $this->assertContains('last_sold', $this->getModelContents('Item'));
        $this->assertContains("protected \$dates = [\n        'last_bought',\n        'last_sold',\n    ];\n",
            $this->getModelContents('Item'));

    }


}
