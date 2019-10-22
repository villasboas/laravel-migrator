<?php

namespace Migrator\Tests;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Migrator\Schema\Migrator\MigrationFile;

class MigratorTest extends BaseTestCase
{
    use GenerateAndRun;

    /** @test */
    public function test1()
    {
        $this->assertTableNotExists('users');

        $this->migrator->migrate('
            namespace App\\Models\\Test1
            
            User
                name: string
                email: string
                number: integer
        ');

        //$this->dump();
        $this->assertTableExists('users');
        $this->assertFileExistsInApp('Models/Test1');
        $this->assertFileExistsInApp('Models/Test1/User.php');
        $this->assertClassExists('App\Models\Test1\User');

        \App\Models\Test1\User::create(['name' => 'user1', 'email' => 'test@test.ru', 'number' => 123]);
        $u = \App\Models\Test1\User::first();
        $this->assertEquals(123, $u->number);
        $this->assertEquals(1, \App\Models\Test1\User::count());
    }

    /** @test */
    public function fields_already_exists()
    {
        $this->generateAndRun('
            namespace {namespace}
            Item
                title: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            Item
                title1: string
                RENAME FIELD title TO title1
        ');

        $this->assertMigrationsNotContain('$table->string(\'title1\')');
        $this->assertMigrationsContain('$table->renameColumn(\'title\', \'title1\');');
        $this->assertMigrationsContain('$table->renameColumn(\'title1\', \'title\');'); // in down

        $this->assertEquals(
            2,
            mb_substr_count(array_values($this->migrator->migrationsCreated)[0], "Schema::table('items'")
        );
    }

    /** @test */
    public function fields_already_exists_down()
    {
        $this->generateAndRun('
            namespace {namespace}
            Item
                title: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            Item
                title1: string
                RENAME FIELD title TO title1
        ');

        $this->assertFieldExists('items', 'title1');
        $this->assertFieldNotExists('items', 'title');

        Artisan::call('migrate:rollback');

        $this->assertFieldExists('items', 'title');
        $this->assertFieldNotExists('items', 'title1');
    }

    /** @test */
    public function cannot_rename_and_have_old_field()
    {
        $message = 'You have a `RENAME FIELD title1` command and you also try to create the same field `title1`';
        $this->expectExceptionMessage($message);
        $this->generateAndRun('
            namespace {namespace}
            Item
                title1: string
                price: decimal(8,2)
                RENAME FIELD title1 TO title2
        ');
    }

    /** @test */
    public function double_call()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                roles()
            
            Role
                users()
        ');

        $this->assertFieldExists('role_user', 'user_id');

        $this->generateAndRun('
            namespace {namespace}
            User
                roles()
            
            Role
                users()
        ');

        // this used to throw an exception
    }

    /** @test */
    public function default_for_boolean()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                is_bender_great: boolean Default(true)
        ');

        $this->assertMigrationsContain('table->boolean(\'is_bender_great\')->nullable()->default(true);');

        $user = $this->newInstanceOf('User');
        $user->save();
        $this->assertEquals('1', $user->fresh()->is_bender_great); // sqlite stores it as 1
    }

    /** @test */
    public function default_for_int_float()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                weight: integer Default(100)
                age: float Default(18.5)
        ');

        $this->assertMigrationsContain('table->integer(\'weight\')->nullable()->default(100);');
        $this->assertMigrationsContain('table->float(\'age\')->nullable()->default(18.5);');

        $user = $this->newInstanceOf('User');
        $user->save();
        $this->assertEquals(18.5, $user->fresh()->age);
        $this->assertEquals(100, $user->fresh()->weight);
    }

    /** @test */
    public function IMPORTANT_custom_raw_types_like_for_nearestneigh()
    {
        $this->markTestIncomplete('TODO@slava: create test IMPORTANT_custom_raw_types_like_for_nearestneigh');
    }

    /** @test */
    public function IMPORTANT_automatically_re_create_index()
    {
        $this->markTestIncomplete('TODO@slava: create test IMPORTANT_automatically_re_create_index');
    }

    /** @test */
    public function IMPORTANT_automatically_re_create_field()
    {
        $this->markTestIncomplete('TODO@slava: create test IMPORTANT_automatically_re_create_index');
    }

    /** @test */
    public function IMPORTANT_undo_last_action()
    {
        $this->markTestIncomplete('TODO@slava: create test undo_last_action');
    }

    /** @test */
    public function IMPORTANT_display_what_has_been_created_modified_models()
    {
        $this->markTestIncomplete('TODO@slava: create test display_what_has_been_created_modified_models');
    }

    /** @test */
    public function rename_field_down()
    {
        $this->generateAndRun('
            namespace {namespace}
            Item
                title: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            Item
                title1: string
                RENAME FIELD title TO title1
        ');

        $this->assertMigrationsNotContain('$table->string(\'title1\')');
        $this->assertMigrationsContain('$table->renameColumn(\'title1\', \'title\');'); // in down

        $this->assertFieldNotExists('items', 'title');
        Artisan::call('migrate:rollback');
        $this->assertFieldExists('items', 'title');
    }

    /** @test */
    public function it_still_creates_duplicate_migration_class_names_between_calls()
    {
        $this->assertFalse(MigrationFile::classFileExists('UpdateUser45sTable'));
        $this->assertFalse(MigrationFile::classFileExists('UpdateUser45sTable'));

        try {
            $filename = database_path('migrations/2018_05_08_195104001_update_user45s_table.php');
            file_put_contents(
                $filename,
                '<?php use Illuminate\Database\Migrations\Migration;
                 class UpdateUser45sTable extends Migration {}'
            );
            $this->assertTrue(MigrationFile::classFileExists('UpdateUser45sTable'));
            $this->assertTrue(MigrationFile::classFileExists('UpdateUser45sTable'));
            $this->generateAndRun('
            namespace {namespace}
            User45
                x: string
            ');
            $this->generateAndRun('
            namespace {namespace}
            User45
                x: string
                y: string
            ');
            $this->assertMigrationsContain('UpdateUser45sTable1');
        } finally {
            @unlink($filename);
        }
    }

    /** @test */
    public function test2()
    {
        $this->assertTableNotExists('users');

        $this->migrator->migrate('
            namespace App\\Models\\Test2
            
            User
                phone()

            Phone
                number: string
                user() via user_id
        ');

        $this->assertTableExists('users');
        $this->assertMigrationsContain("\$table->integer('user_id')->nullable();", 'phone');
        $this->assertModelsContain("hasOne(\App\Models\Test2\Phone::class)", 'User');
        $this->assertModelsContain("belongsTo(\App\Models\Test2\User::class)", 'Phone');

        $user = \App\Models\Test2\User::create();
        $user->phone()->create(['number' => '123']);
        $user->save();

        $this->assertEquals('123', $user->fresh()->phone->number);
    }

    /** @test */
    public function test_not_null()
    {
        $this->assertTableNotExists('users');

        $this->migrator->migrate('
            default not null

            namespace App\\Models\\Test2
            
            User
                phone()

            Phone
                number: string
                user() via user_id
        ', false);
        $this->assertNotNull($this->migrator->getSchema()->getModel('Phone')->getField('number')->getSchema());
        $this->migrator->applySchema();

        $this->assertTableExists('users');
        $this->assertMigrationsContain("\$table->integer('user_id');", 'phone');
    }

    /** @test */
    public function one_to_one()
    {
        $this->migrator->migrate('
            namespace App\\Models\\Test3
            User
                phone()
                
            Phone
                user() via user_id
        ');

        $this->assertMigrationsContain("\$table->integer('user_id')->nullable();", 'phone');
        $this->assertModelsContain("hasOne(\App\Models\Test3\Phone::class)", 'User');
    }

    /** @test */
    public function one_to_one_complex()
    {
        $this->migrator->migrate('
            namespace App\\Models\\Test4
            User
                user_primary_key: increments PrimaryKey
                phone()
                
            Phone
                phone_primary_key: increments PrimaryKey
                number: string
                user() via user_id
        ');

        $this->assertMigrationsNotContain("\$table->increments('id');", 'user');
        $this->assertMigrationsContain("\$table->increments('user_primary_key')->nullable();", 'user');

        $this->assertMigrationsNotContain("\$table->increments('id');", 'phone');
        $this->assertMigrationsContain("\$table->increments('phone_primary_key')->nullable();", 'phone');

        $this->assertModelsContain("belongsTo(\App\Models\Test4\User::class, 'user_id', 'user_primary_key')", 'Phone');
        $this->assertModelsContain("hasOne(\App\Models\Test4\Phone::class, 'user_id', 'user_primary_key')", 'User');

        $this->assertModelsContain("protected \$primaryKey = 'user_primary_key'", 'User');
        $this->assertModelsContain("protected \$primaryKey = 'phone_primary_key'", 'Phone');

        $user = \App\Models\Test4\User::create();
        $user->phone()->create(['number' => '123']);
        $user->save();
        $this->assertNotNull($user->user_primary_key);

        $this->assertEquals('123', $user->fresh()->phone->number);
    }

    /** @test */
    public function one_to_one_complex2()
    {
        $this->generateButDontRun('
            namespace App\\Models\\Test5
            User
                user_primary_key: increments PrimaryKey
                phone()
                
            Phone
                phone_primary_key: increments PrimaryKey
                user() via weird_user_id
        ');

        $this->assertMigrationsNotContain("\$table->increments('id');", 'user');
        $this->assertMigrationsContain("\$table->increments('user_primary_key')->nullable();", 'user');

        $this->assertMigrationsNotContain("\$table->increments('id');", 'phone');
        $this->assertMigrationsContain("\$table->increments('phone_primary_key')->nullable();", 'phone');

        $this->assertModelsContain(
            "belongsTo(\App\Models\Test5\User::class, 'weird_user_id', 'user_primary_key')",
            'Phone'
        );
        $this->assertModelsContain(
            "hasOne(\App\Models\Test5\Phone::class, 'weird_user_id', 'user_primary_key')",
            'User'
        );

        $this->assertModelsContain("protected \$primaryKey = 'user_primary_key'", 'User');
        $this->assertModelsContain("protected \$primaryKey = 'phone_primary_key'", 'Phone');
    }

    /** @test */
    public function numbered_types()
    {
        /* @see https://laravel.com/docs/5.6/migrations#creating-columns */

        // char(100)
        // decimal(8, 2);
        // decimal(8);
        // double(8, 2);
        // float(8, 2);
        // string(100);
        // unsignedDecimal(8, 2)
        // enum('level', ['easy', 'hard']
        $this->generateButDontRun("
            namespace App\Models\TestNumberedTypes
            
            Type
                c: char(100)
                d: decimal(8)
                d1: decimal(8, 2)
                f: float(8,2)
                s: string(100)
                ud: unsignedDecimal(8, 2)
                en: enum(['easy','hard'])
        ");

        $this->assertMigrationsContain("\$table->char('c', 100)->nullable();");
        $this->assertMigrationsContain("\$table->decimal('d', 8)->nullable();");
        $this->assertMigrationsContain("\$table->decimal('d1', 8, 2)->nullable();");
        $this->assertMigrationsContain("\$table->float('f', 8, 2)->nullable();");
        $this->assertMigrationsContain("\$table->string('s', 100)->nullable();");
        $this->assertMigrationsContain("\$table->unsignedDecimal('ud', 8, 2)->nullable();");
        $this->assertMigrationsContain("\$table->enum('en', ['easy','hard'])->nullable();");
    }

    /** @test */
    public function one_to_many()
    {
        $this->generateAndRun("
            namespace App\Models\Test6
            
            Post
                comments()
                
            Comment
                post()
        ");

        $this->assertMigrationsContain("\$table->increments('id');", 'post');
        $this->assertMigrationsContain("\$table->increments('id');", 'comment');

        $post = \App\Models\Test6\Post::create();
        $comment = $post->comments()->create()->fresh();
        $this->assertEquals(1, \App\Models\Test6\Post::first()->comments()->count());
        $this->assertEquals($comment, \App\Models\Test6\Post::first()->comments()->first());
    }

    /** @test */
    public function one_to_many_complex()
    {
        $this->generateAndRun("
            namespace App\Models\Test7
            
            Post
                post_primary_key: increments PrimaryKey
                comments()
                
            Comment
                comment_primary_key: increments PrimaryKey
                post() via weird_id
        ");

        $this->assertMigrationsNotContain("\$table->increments('id');", 'post');
        $this->assertMigrationsContain("\$table->increments('post_primary_key')->nullable();", 'post');

        $this->assertMigrationsNotContain("\$table->increments('id');", 'comment');
        $this->assertMigrationsContain("\$table->increments('comment_primary_key')->nullable();", 'comment');

        $this->assertModelsContain(
            "belongsTo(\App\Models\Test7\Post::class, 'weird_id', 'post_primary_key')",
            'Comment'
        );
        $this->assertModelsContain(
            "hasMany(\App\Models\Test7\Comment::class, 'weird_id', 'post_primary_key')",
            'Post'
        );

        $this->assertModelsContain("protected \$primaryKey = 'post_primary_key'", 'Post');
        $this->assertModelsContain("protected \$primaryKey = 'comment_primary_key'", 'Comment');
    }

    /** @test */
    public function many_to_many()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                roles()
                
            Role
                name: string
                users()
        ');

        //$this->dumpModels();
        $this->assertMigrationsContain("Schema::create('role_user', function (Blueprint \$table) {");
        $this->assertMigrationsContain("\$table->unsignedInteger('user_id');");
        $this->assertMigrationsContain("\$table->unsignedInteger('role_id');");
        $this->assertMigrationsContain("Schema::drop('role_user');");

        $this->assertModelsContain("\$this->belongsToMany(\\$ns\User::class, 'role_user');");

        $this->assertTableExists('role_user');

        $role = $this->newInstanceOf('Role')->create(['name' => 'Admin']);
        $user = $this->newInstanceOf('User')->create(['name' => 'Mr X']);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach($role);
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals(['Admin'], $user->roles()->pluck('name')->all());
    }

    /** @test */
    public function m2m_with_timestamps()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                podcasts(): Podcast[] PivotWithTimestamps
                
            Podcast
                name: string
                users() 
        ');

        $this->assertModelsContain("belongsToMany(\\$ns\Podcast::class, 'podcast_user')->withTimestamps();");

        $user = $this->newInstanceOf('User')->create([]);
        $podcast = $this->newInstanceOf('Podcast')->create([]);
        $user->podcasts()->attach($podcast);
        $this->assertNotNull($user->fresh()->podcasts[0]->pivot->created_at);
    }

    /** @test */
    public function m2m_timestamps()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                podcasts(): Podcast[] As("subscription"), PivotWithTimestamps
                
            Podcast
                name: string
                users() 
        ');

        $this->assertModelsContain("belongsToMany(\\$ns\Podcast::class, 'podcast_user')->as('subscription')->withTimestamps();");

        $user = $this->newInstanceOf('User')->create([]);
        $podcast = $this->newInstanceOf('Podcast')->create([]);
        $user->podcasts()->attach($podcast);
        $this->assertNotNull($user->fresh()->podcasts[0]->subscription->created_at);
    }

    /** @test */
    public function m2m_model()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            AssignedRole
                role_pk1: unsignedInteger
                user_pk1: unsignedInteger
                expires: integer
            
            User
                user_pk: integer
                name: string
                roles(): Role[] Join(Role.role_pk = AssignedRole.role_pk1 AND AssignedRole.user_pk1 = User.user_pk)
                
            Role
                role_pk: integer
                name: string
                users(): User[] 
        ');

        $this->assertMigrationsContain("Schema::create('assigned_roles', function (Blueprint \$table) {");
        $this->assertMigrationsContain("\$table->unsignedInteger('user_pk1')->nullable();", 'assigned_roles');
        $this->assertMigrationsContain("\$table->unsignedInteger('role_pk1')->nullable();", 'assigned_roles');
        $this->assertMigrationsContain("Schema::drop('assigned_roles');");
        $this->assertModelsContain("class AssignedRole extends Pivot\n{", 'AssignedRole');

        // see "Defining Custom Intermediate Table Models" in https://laravel.com/docs/5.6/eloquent-relationships#many-to-many
        $s = "\$this->belongsToMany(\\$ns\User::class, 'assigned_roles', 'user_pk1', 'role_pk1')->using(\\$ns\AssignedRole::class)->withPivot(";
        $this->assertModelsContain($s, 'role');

        $this->assertTableExists('assigned_roles');

        $role = $this->newInstanceOf('Role')->create(['name' => 'Admin']);
        $user = $this->newInstanceOf('User')->create(['name' => 'Mr X']);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach($role, ['expires' => 123]);
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals(['Admin'], $user->roles()->pluck('name')->all());

        $this->assertEquals(123, $user->roles[0]->pivot->expires);
    }

    /** @test */
    public function datetime_time_etc()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                what_time: datetime
                what_time2: date
                what_time3: dateTimeTz
        ');

        $this->assertModelsContain("protected \$dates = [\n        'what_time',\n        'what_time2',\n        'what_time3',");
        $user = $this->newInstanceOf('User')->create([
            'what_time'  => Carbon::now(),
            'what_time2' => Carbon::now(),
            'what_time3' => Carbon::now(),
        ]);

        $this->assertNotNull($user->fresh()->what_time);
        $this->assertInstanceOf(Carbon::class, $user->fresh()->what_time);
        $this->assertEquals(0, now()->diffInMinutes($user->fresh()->what_time));

        $this->assertNotNull($user->fresh()->what_time2);
        $this->assertInstanceOf(Carbon::class, $user->fresh()->what_time2);
        $this->assertEquals(0, now()->diffInMinutes($user->fresh()->what_time2));

        $this->assertNotNull($user->fresh()->what_time3);
        $this->assertInstanceOf(Carbon::class, $user->fresh()->what_time3);
        $this->assertEquals(0, now()->diffInMinutes($user->fresh()->what_time3));
    }

    /** @test */
    public function default_value()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Default("Mr.Y")
        ');

        $u = $this->newInstanceOf('User');
        $u->save();
        $this->assertEquals('Mr.Y', $u->fresh()->name);
    }

    /** @test */
    public function app_namespace()
    {
        $this->generateAndRun('
            User
                name: string
        ');

        $this->assertModelsContain('namespace App;', 'app/User.php');
    }

    /** @test */
    public function works_with_json()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                history: json
        ');

        $this->assertModelsContain("'history' => 'json',");
        $user = $this->newInstanceOf('User')->create(['history' => [1, 2, 3]]);
        $this->assertEquals([1, 2, 3], $user->fresh()->history);
    }

    /** @test */
    public function works_with_jsonb()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                history: jsonb
        ');

        $this->assertModelsContain("'history' => 'json',");
        $user = $this->newInstanceOf('User')->create(['history' => [1, 2, 3]]);
        $this->assertEquals([1, 2, 3], $user->fresh()->history);
    }

    /** @test */
    public function works_with_collection()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                history: collection
        ');

        $this->assertMigrationsContain("\$table->text('history')->nullable();");
        $this->assertModelsContain("'history' => 'collection',");
        $user = $this->newInstanceOf('User')->create(['history' => [1, 2, 3]]);
        $this->assertEquals([1, 2, 3], $user->fresh()->history->all());
    }

    /** @test */
    public function works_with_array()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                history: array
        ');

        $this->assertMigrationsContain("\$table->text('history')->nullable();");
        $this->assertModelsContain("'history' => 'array',");
        $user = $this->newInstanceOf('User')->create(['history' => [1, 2, 3]]);
        $this->assertEquals([1, 2, 3], $user->fresh()->history);
    }

    /** @test */
    public function creates_fields_in_existing_tables()
    {
        $this->overwriteModels();

        $this->assertTableNotExists('users');

        $this->generateAndRun('
            namespace {namespace}
            
            User
                name:string
        ');

        $this->assertTableExists('users');
        $this->assertMigrationsContain("Schema::drop('users'");

        $this->generateButDontRun("
            namespace {$this->ns}
            
            User
                history: array
        ");

        $this->assertMigrationsNotContain('CreateUsersTable');
        $this->assertMigrationsContain('UpdateUsersTable');
        $this->assertMigrationsContain("Schema::table('users', function (Blueprint \$table)");
        $this->assertMigrationsContain("\$table->dropColumn('history');");

        $this->runGenerated();

        $user = $this->newInstanceOf('User')->create(['name' => 'User', 'history' => [1, 2, 3]]);
        $this->assertEquals('User', $this->newInstanceOf('User')->first()->name);
        $this->assertEquals([1, 2, 3], $this->newInstanceOf('User')->first()->history);

        $this->generateAndRun("
            namespace {$this->ns}
            
            User
                history1: array
        ");

        $this->assertMigrationsNotContain('UpdateUsersTable '); // should be UpdateUsersTable1/2/3, depending on test runs
        $this->assertMigrationsNotContain('$table->dropColumn(\'history\');');
        $this->assertMigrationsContain('$table->dropColumn(\'history1\');');

        $user->update(['history1' => '222']);
        $this->assertEquals('222', $this->newInstanceOf('User')->first()->history1);

        Artisan::call('migrate:rollback');

        $this->assertEquals(null, $this->newInstanceOf('User')->first()->history1);
    }

    /** @test */
    public function creates_indexes()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                title: string Index
        ');

        $this->assertMigrationsContain("\$table->index(['title'], 'items_title_idx');");
    }

    /** @test */
    public function creates_indexes1()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                title: string Index(i1_idx)
        ');

        $this->assertMigrationsContain("\$table->index(['title'], 'i1_idx')");
    }

    /** @test */
    public function creates_indexes2()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                title: string
        ');

        $this->assertMigrationsNotContain("\$table->index(['title'], 'items_title_idx')");
        $this->assertIndexNotExists('items', 'items_title_idx');

        $this->generateAndRun("
            namespace {$this->ns}
            
            Item
                title: string Index
        ");

        $this->assertMigrationsContain("\$table->index(['title'], 'items_title_idx')");
        $this->assertMigrationsContain("\$table->dropIndex('items_title_idx')");
        $this->assertIndexExists('items', 'items_title_idx');

        $this->generateButDontRun("
            namespace {$this->ns}
            
            Item
                title: string Index
                price: decimal(8,2) Index(price_idx)
                price2: decimal(8,2) Index(price_idx)
        ");

        $this->assertEquals(
            'decimal',
            $this->migrator->getSchema()->getModel('Item')->getField('price')->getFieldType()
        );

        $this->assertMigrationsNotContain("\$table->index(['title'], 'items_title_idx')");
        $this->assertMigrationsNotContain("\$table->dropIndex('items_title_idx')");

        $this->assertMigrationsContain("\$table->decimal('price', 8, 2)->nullable();");
        $this->assertMigrationsContain("\$table->index(['price', 'price2'], 'price_idx');");

        $this->runGenerated();
    }

    /** @test */
    public function creates_index_with_multiple_fields()
    {
        $this->generateButDontRun('
            namespace {namespace}
            
            Item
                title: string Index
                price: decimal(8, 2) Index(price_idx)
                price2: decimal(8, 2) Index(price_idx)
        ');

        $this->assertMigrationsContain("\$table->decimal('price', 8, 2)->nullable();");
        $this->assertMigrationsContain("\$table->index(['price', 'price2'], 'price_idx');");
        $this->runGenerated();

        $this->assertIndexExists('items', 'price_idx');

        Artisan::call('migrate:rollback');

        $this->assertTableNotExists('items');
        $this->assertIndexNotExists('items', 'price_idx');
    }

    /** @test */
    public function changes_fields_definitions_nullable()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                price: integer
        ');

        $this->generateAndRun('
            namespace {namespace}
            
            Item
                price: integer NotNull
        ');
        $this->assertCount(1, $this->migrator->migrationsCreated);
        $this->assertMigrationsContain("public function up()\n    {\n        Schema::table('items', function (Blueprint \$table) {\n            \$table->integer('price')->nullable(false)->change();");
        $this->assertMigrationsContain("public function down()\n    {\n        Schema::table('items', function (Blueprint \$table) {\n            \$table->integer('price')->nullable(true)->change();");

        $this->generateAndRun('
            namespace {namespace}
            
            Item
                price: integer NotNull
        ');

        $this->assertEquals([], $this->migrator->migrationsCreated);
    }

    /** @test */
    public function updates_existing_model_files()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                name: string
            
            Phone
                phone: string
        ');

        $this->generateAndRun('
            namespace {namespace}

            User
                name: string
                phone()
                
            Phone
                phone: string
                user() via user_id
        ');

        $this->assertStringContainsString('phone()', $this->getModelContents('User'));
        //$this->dumpModels();

        $user = $this->newInstanceOf('User')->create();
        $user->phone()->create([]);
        $this->assertEquals(1, $this->newInstanceOf('User')->first()->phone()->count());
    }

    /** @test */
    public function updates_existing_model_files2()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                name: string
            
            Phone
                phone: string
        ');

        $this->generateAndRun('
            namespace {namespace}

            User
                name: string
                phone()
                
            Phone
                phone: string
                user() via user_id
        ');

        $this->generateAndRun('
            namespace {namespace}

            User
                name: string
                phone()
                
            Phone
                phone: string
                user() via user_id
        ');

        $this->assertStringContainsString('phone()', $this->getModelContents('User'));
        //$this->dumpModels();

        $user = $this->newInstanceOf('User')->create();
        $user->phone()->create([]);
        $this->assertEquals(1, $this->newInstanceOf('User')->first()->phone()->count());
    }

    /** @test */
    public function correctly_changes_fields_upon_down()
    {
        $this->markTestIncomplete('TODO@slava: create test correctly_changes_fields_upon_down');
    }

    /** @test */
    public function migrations_should_have_more_meaningful_names()
    {
        $this->markTestIncomplete('TODO@slava: create test migrations_should_have_more_meaningful_names');
    }

    /** @test */
    public function renames_fields()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                mame: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                RENAME FIELD mame TO name
        ');

        $this->assertMigrationsContain("\$table->renameColumn('mame', 'name');");

        $user = $this->newInstanceOf('User')->create(['name' => 'test']);
        $this->assertEquals('test', $user->fresh()->name);
    }

    /** @test */
    public function renames_fields_but_not_twice()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                mame: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                RENAME FIELD mame TO name
        ');

        $this->assertMigrationsContain("\$table->renameColumn('mame', 'name');");

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                RENAME FIELD mame TO name
        ');

        $this->assertCount(0, $this->migrator->migrationsCreated);

        $user = $this->newInstanceOf('User')->create(['name' => 'test']);
        $this->assertEquals('test', $user->fresh()->name);
    }

    /** @test */
    public function deletes_fields()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                mame: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                DELETE FIELD mame
        ');

        $this->assertMigrationsContain("\$table->dropColumn('mame');");

        $user = $this->newInstanceOf('User')->create(['name' => 'test']);
        $this->assertNull($user->fresh()->mame);
        $this->assertEquals('test', $user->fresh()->name);
    }

    /** @test */
    public function deletes_fields_but_not_twice()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                mame: string
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                DELETE FIELD mame
        ');

        $this->assertMigrationsContain("\$table->dropColumn('mame');");

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                DELETE FIELD mame
        ');

        $this->assertCount(0, $this->migrator->migrationsCreated);

        $user = $this->newInstanceOf('User')->create(['name' => 'test']);
        $this->assertNull($user->fresh()->mame);
        $this->assertEquals('test', $user->fresh()->name);
    }

    /** @test */
    public function renames_indexes()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Index(i1)
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Index(i2) 
                RENAME INDEX i1 TO i2
        ');

        $this->assertMigrationsNotContain("\$table->index('i2'");
        $this->assertMigrationsContain("\$table->renameIndex('i1', 'i2');");

        $user = $this->newInstanceOf('User')->create(['name' => 'test']);
        $this->assertEquals('test', $user->fresh()->name);

        $this->assertIndexNotExists('users', 'i1');
        $this->assertIndexExists('users', 'i2');
    }

    /** @test */
    public function renames_indexes_bad()
    {
        $message = 'You have a `RENAME INDEX i1` command and you also try to create Index(i1) on a field `User.name`';
        $this->expectExceptionMessage($message);

        $this->generateAndRun('
            namespace {namespace}
            User
                mame: string Index(i1)
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Index(i1) 
                RENAME INDEX i1 TO i2
        ');
    }

    /** @test */
    public function deletes_indexes()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Index(i1)
        ');

        $this->assertIndexExists('users', 'i1');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                DELETE INDEX i1
        ');

        $this->assertMigrationsContain("\$table->dropIndex('i1');");

        $this->assertIndexNotExists('users', 'i1');
    }

    /** @test */
    public function guarded_fields()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            User
                is_admin: boolean Guarded
                is_admin2: boolean Guarded
        ');

        $this->assertModelsContain("protected \$guarded = ['is_admin', 'is_admin2'];");
    }

    /** @test */
    public function deletes_indexes_bad()
    {
        $message = 'You have a `DELETE INDEX i2` command and you also try to create Index(i2) on a field `User.name`.';
        $this->expectExceptionMessage($message);

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Index(i1)
        ');

        $this->generateAndRun('
            namespace {namespace}
            User
                name: string Index(i2) 
                DELETE INDEX i2
        ');
    }

    /** @test */
    public function creates_unique()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                title: string Unique
        ');

        $this->assertMigrationsContain("\$table->unique(['title'], 'items_title_unique_idx');");
    }

    /** @test */
    public function creates_unique1()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                title: string Unique(i1_idx)
        ');

        $this->assertMigrationsContain("\$table->unique(['title'], 'i1_idx')");
    }

    /** @test */
    public function creates_unique2()
    {
        $this->generateAndRun('
            namespace {namespace}
            
            Item
                title: string
        ');

        $this->assertMigrationsNotContain("\$table->unique(['title'], 'items_title_unique_idx')");
        $this->assertIndexNotExists('items', 'items_title_unique_idx');

        $this->generateAndRun("
            namespace {$this->ns}
            
            Item
                title: string Unique
        ");

        $this->assertMigrationsContain("\$table->unique(['title'], 'items_title_unique_idx')");
        $this->assertMigrationsContain("\$table->dropUnique('items_title_unique_idx')");
        $this->assertIndexExists('items', 'items_title_unique_idx');

        $this->generateButDontRun("
            namespace {$this->ns}
            
            Item
                title: string Unique
                price: decimal(8,2) Unique(price_idx)
                price2: decimal(8,2) Unique(price_idx)
        ");

        $this->assertEquals(
            'decimal',
            $this->migrator->getSchema()->getModel('Item')->getField('price')->getFieldType()
        );

        $this->assertMigrationsNotContain("\$table->unique(['title'], 'items_title_unique_idx')");
        $this->assertMigrationsNotContain("\$table->dropUnique('items_title_unique_idx')");

        $this->assertMigrationsContain("\$table->decimal('price', 8, 2)->nullable();");
        $this->assertMigrationsContain("\$table->unique(['price', 'price2'], 'price_idx');");

        $this->runGenerated();
    }

    /** @test */
    public function creates_primary_key()
    {
        $this->markTestIncomplete('TODO@slava: create test');
    }

    /** @test */
    public function adds_annotations_fields_to_models()
    {
        $this->markTestIncomplete('TODO@slava: create test adds_annotations_fields_to_models');
    }

    /** @test */
    public function gin_gist_indexes()
    {
        $this->markTestIncomplete('TODO@slava: create test gin_gist_indexes');
    }

    /** @test */
    public function spatial_indexes()
    {
        $this->markTestIncomplete('TODO@slava: create test spatial_indexes');
    }

    /** @test */
    public function default_value_with_comma_quotes_etc()
    {
        $this->markTestIncomplete('TODO@slava: create test default_value_with_comma_quotes_etc');
    }

    /** @test */
    public function adds_fields_to_indices()
    {
        $this->markTestIncomplete('TODO@slava: create test adds_fields_to_indices');
    }

    /** @test */
    public function m2m_model_join()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            User
                user_pk: integer
                name: string
                roles(): Role[] Join(Role.role_pk = assigned_roles.role_pk1 AND assigned_roles.user_pk1 = User.user_pk)
                
            Role
                role_pk: integer
                name: string
                users(): User[] 
        ');

        //$this->dumpModels();
        $this->assertMigrationsContain("Schema::create('assigned_roles', function (Blueprint \$table) {");
        $this->assertMigrationsContain("\$table->unsignedInteger('user_pk1');");
        $this->assertMigrationsContain("\$table->unsignedInteger('role_pk1');");
        $this->assertMigrationsContain("Schema::drop('assigned_roles');");

        $this->assertModelsContain("\$this->belongsToMany(\\$ns\User::class, 'assigned_roles'");

        $this->assertTableExists('assigned_roles');

        $role = $this->newInstanceOf('Role')->create(['name' => 'Admin']);
        $user = $this->newInstanceOf('User')->create(['name' => 'Mr X']);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach($role);
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals(['Admin'], $user->roles()->pluck('name')->all());
    }

    /** @test */
    public function many_to_many_pk()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            User
                user_pk: increments PrimaryKey
                name: string
                roles()
                
            Role
                role_pk: increments PrimaryKey
                name: string
                users()
        ');

        $this->assertMigrationsContain("Schema::create('role_user', function (Blueprint \$table) {");
        $this->assertMigrationsContain("\$table->unsignedInteger('user_id');");
        $this->assertMigrationsContain("\$table->unsignedInteger('role_id');");
        $this->assertModelsContain(
            "\$this->belongsToMany(\\$ns\Role::class, 'role_user', 'user_id', 'role_id', 'user_pk', 'role_pk');",
            'User'
        );
        $this->assertModelsContain(
            "\$this->belongsToMany(\\$ns\User::class, 'role_user', 'role_id', 'user_id', 'role_pk', 'user_pk');",
            'Role'
        );

        $this->assertTableExists('role_user');

        $role = $this->newInstanceOf('Role')->create(['name' => 'Admin']);
        $user = $this->newInstanceOf('User')->create(['name' => 'Mr X']);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach($role);
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals(['Admin'], $user->roles()->pluck('name')->all());
    }

    /** @test */
    public function many_to_many_pk1()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            User
                name: string
                roles()
                
            Role
                role_pk: increments PrimaryKey
                name: string
                users()
        ');

        $role = $this->newInstanceOf('Role')->create(['name' => 'Admin']);
        $user = $this->newInstanceOf('User')->create(['name' => 'Mr X']);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach($role);
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals(['Admin'], $user->roles()->pluck('name')->all());
    }

    /** @test */
    public function many_to_many_pk2()
    {
        $this->generateAndRun('
            namespace {namespace}
            User
                user_pk: increments PrimaryKey
                name: string
                roles()
                
            Role
                name: string
                users()
        ');

        $role = $this->newInstanceOf('Role')::create(['name' => 'Admin']);
        $user = $this->newInstanceOf('User')::create(['name' => 'Mr X']);
        $this->assertEquals(0, $user->roles()->count());
        $user->roles()->attach($role);
        $this->assertEquals(1, $user->roles()->count());
        $this->assertEquals(['Admin'], $user->roles()->pluck('name')->all());
    }

    /** @test */
    public function has_many_through()
    {
        /** @see https://laravel.com/docs/5.6/eloquent-relationships#has-many-through */
        $ns = $this->generateAndRun('
            namespace {namespace}
            Country
                name: string
                users()
                posts() via User
                
            User
                name: string
                posts()
                country()
                
            Post
                title: string
                user()
        ');

        $this->assertMigrationsNotContain('User', 'countries');
        $this->assertModelsContain(
            "hasManyThrough(\\{$ns}\Post::class, \\{$ns}\User::class, 'country_id', 'user_id');",
            'country'
        );

        $country = $this->newInstanceOf('Country')->create(['name' => 'Sweden']);
        $user = $country->users()->create(['name' => 'Johansson']);
        $user->posts()->create(['title' => 'It is great weather in Sweden in summer']);

        $this->assertEquals(1, $country->posts()->count());
    }

    /** @test */
    public function has_many_through_complex()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            Country
                country_pk: increments PrimaryKey
                name: string
                users()
                posts() via User
                
            User
                user_pk: increments PrimaryKey
                name: string
                posts()
                country()
                
            Post
                post_pk: increments PrimaryKey
                title: string
                user()
        ');

        $this->assertMigrationsNotContain('User', 'countries');
        $this->assertModelsContain(
            "hasManyThrough(\\$ns\Post::class, \\$ns\User::class, 'country_id', 'user_id', 'country_pk', 'user_pk')",
            'country'
        );

        $country = $this->newInstanceOf('Country')->create(['name' => 'Sweden']);
        $user = $country->users()->create(['name' => 'Johansson']);
        $user->posts()->create(['title' => 'It is great weather in Sweden in summer']);

        $this->assertEquals(1, $country->posts()->count());
    }

    /** @test */
    public function polymorphic_relations()
    {
        /** @see https://laravel.com/docs/5.6/eloquent-relationships#polymorphic-relations */
        $ns = $this->generateAndRun('
            namespace {namespace}
            
            Post
                title: string
                comments()
                
            Video
                title: string
                comments()
                
            Comment
                body: string
                commentable(): Post|Video
            
        ');

        $post = $this->newInstanceOf('Post')->create(['title' => 'Summer in Tuscany']);
        $post->comments()->create(['body' => 'I love it!']);

        $video = $this->newInstanceOf('Post')->create(['title' => 'Magic Trick']);
        $video->comments()->create(['body' => 'How did he do it?']);

        $this->assertEquals(['I love it!'], $post->fresh()->comments->pluck('body')->all());
        $this->assertEquals(['How did he do it?'], $video->fresh()->comments->pluck('body')->all());
    }

    /** @test */
    public function polymorphic_relations_complex()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            
            Post
                post_pk: increments PrimaryKey
                title: string
                comments()
                
            Video
                video_pk: increments PrimaryKey
                title: string
                comments()
                
            Comment
                comment_pk: increments PrimaryKey
                body: string
                commentable(): Post|Video
            
        ');

        $post = $this->newInstanceOf('Post')->create(['title' => 'Summer in Tuscany']);
        $post->comments()->create(['body' => 'I love it!']);

        $video = $this->newInstanceOf('Post')->create(['title' => 'Magic Trick']);
        $comment2 = $video->comments()->create(['body' => 'How did he do it?']);

        $this->assertEquals(['I love it!'], $post->first()->comments->pluck('body')->all());
        $this->assertEquals(['How did he do it?'], $video->fresh()->comments->pluck('body')->all());

        $this->assertEquals($post->title, $this->newInstanceOf('Comment')->first()->commentable->title);
        $this->assertEquals($video->title, $comment2->fresh()->commentable->title);
    }

    /** @test */
    public function many_to_many_polymorphic_relations()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            Post
                name: string
                tags() via Taggable
                
            Video
                name: string
                tags() via Taggable
            
            Tag
                name: string
                posts() via Taggable
                videos() via Taggable
                taggables()
                
            Taggable
                tag()
                taggable(): Post|Video
        ');

        $post = $this->newInstanceOf('Post')->create(['name' => 'Summer in Tuscany']);
        $post->tags()->create(['name' => 'tag1']);

        $video = $this->newInstanceOf('Video')->create(['name' => 'Magic Trick']);
        $video->tags()->create(['name' => 'tag2']);

        $this->assertEquals(['tag1'], $post->first()->tags->pluck('name')->all());
        $this->assertEquals(['tag2'], $video->fresh()->tags->pluck('name')->all());

        $this->assertEquals([$post->name], $this->newInstanceOf('Tag')->first()->posts()->pluck('name')->all());
        $tag2 = $this->newInstanceOf('Tag')->where('name', 'tag2')->first();
        $this->assertEquals([$video->name], $tag2->videos()->pluck('name')->all());
    }

    /** @test */
    public function many_to_many_polymorphic_relations_complex()
    {
        $this->expectExceptionMessage('Currently, we don\'t support polymorphic many-to-many with custom Primary Key ');

        $ns = $this->generateAndRun('
            namespace {namespace}
            Post
                post_pk: increments PrimaryKey
                name: string
                tags() via Taggable
                
            Video
                video_pk: increments PrimaryKey
                name: string
                tags() via Taggable
            
            Tag
                tag_pk: increments PrimaryKey
                name: string
                posts() via Taggable
                videos() via Taggable
                taggables()
                
            Taggable
                taggable_pk: increments PrimaryKey
                tag()
                taggable(): Post|Video
        ');

        $post = $this->newInstanceOf('Post')->create(['name' => 'Summer in Tuscany']);
        $post->tags()->create(['name' => 'tag1']);

        $video = $this->newInstanceOf('Video')->create(['name' => 'Magic Trick']);
        $video->tags()->create(['name' => 'tag2']);

        $this->assertEquals(['tag1'], $post->first()->tags->pluck('name')->all());
        $this->assertEquals(['tag2'], $video->fresh()->tags->pluck('name')->all());

        $this->assertEquals([$post->name], $this->newInstanceOf('Tag')->first()->posts()->pluck('name')->all());
        $tag2 = $this->newInstanceOf('Tag')->where('name', 'tag2')->first();
        $this->assertEquals([$video->name], $tag2->videos()->pluck('name')->all());
    }

    /** @test */
    public function self_reference()
    {
        $ns = $this->generateAndRun('
            namespace {namespace}
            Employee
                name: string
                boss() via boss_id: Employee <- Employee.employees()
                employees(): Employee[] <- Employee.boss()
        ');

        $m = $this->migrator->getSchema()->getModel('Employee')->getMethod('boss');
        $this->assertEquals('boss_id', $m->getVia());

        $m = $this->migrator->getSchema()->getModel('Employee')->getMethod('employees');
        $this->assertTrue($m->isHasMany());
        $this->assertFalse($m->isBelongsTo());

        $this->assertModelsContain("return \$this->belongsTo(\\$ns\\Employee::class, 'boss_id');");
        $this->assertModelsContain("return \$this->hasMany(\\$ns\\Employee::class, 'boss_id');");

        $boss = $this->newInstanceOf('Employee');
        $boss->name = 'Boss';
        $boss->save();

        $jack = $boss->employees()->create(['name' => 'Jack']);
        $boss->save();

        $this->assertEquals('Boss', $jack->boss->name);
        $this->assertEquals('Jack', $boss->employees[0]->name);
    }
}
