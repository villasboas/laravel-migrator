# What is this?

Manage your database and models in Laravel 5.x by defining the target schema (migrations are generated automatically).

# Example

Create a file `database/schema.txt`:
```
Human
    name: string
```

Run: 

```
php artisan migrator
```

This: 

1. Generates a migration to create `humans` table with `name` field 
2. Generates/updates `App\Human` model

Change the `database/schema.txt` to this:

```
Human
    name: string
    height: float
```

And again, `php artisan migrator`.

Now you have 2 migrations and 2 fields in the database.

Want more?

```
Human
    name: string
    height: float
    pets()

Pet
    human()
```

... and now you have methods like `App\Human::first()->pets()->first()` and `App\Pet::first()->human`
and `human_id` on `pets` table.

(Everything related to database is done through migrations, model code changes are non-reversible automatically, at least for now) 

**But wait, there's more!** 

* Generate fields
* Generate relationships (one to one, one to many, many to many, has many through, polymorphic)
* Many to many with automatic pivot table creation
* Generate relationship methods on models, creation of indexes 
* Create indexes, composite indexes, unique and composite unique indexes
* Rename and delete fields and indexes (renaming of indexes requires Laravel 5.6.23+, with [my small contribution](https://github.com/laravel/framework/pull/24147) to the framework) 
* Generate/update `$casts` attributes for collection, array, and JSON types
* Default values 
* Self-reference
* Enums 
* Guarded fields 
* Custom pivot tables and field names, `as`, `withTimestamps`, etc...
* Custom namespace and paths for models
* Composite primary keys 
* Date and Time with automatic `$casts` to Carbon models
* ...probably something else too (read all about it below)  

Note: by default all generated fields are `nullable` (yes, you can change this to default to `not nullable`, [see below](#null)).

# Installation

```
composer require --dev slava-vishnyakov/laravel-migrator
``` 

(The `--dev` flag is here because you don't need this on production servers, the migrations
will be generated and run as usual without this package, the only caveat is that if you need
to rename indexes - you need `"doctrine/dbal": "~2.0"` on production)

## Status: alpha testing, so be warned:

> Note: The package works, but it is in very early stages, be careful. 

> Do **git commits** before you run it 
> (it will change your models files, hopefully correctly, but just in case - 
> you have an option to `git reset --hard`) 
> and back up your local development databases periodically! 

> It shouldn't do anything malicious/bad, but the package is pretty new and possibly unstable

> Does it work? Well, there are 3000+ lines of tests, so it should... hopefully...

# Longer usage example

Create a file `database/schema.txt`:
```
Human
    name: string
    height: float
    pets()
    pet_tags() via Pet: Tag[]
    
Pet
    name: string Default("Kitty"), NotNull
    born: datetime
    nicknames: array
    human()
    colors()
    tags()
    
Color
    color: string NotNull
    pets()
    
Tag
    material: enum(['copper','gold'])
    pet()
```

and then: 

```
php artisan migrator
```

This will run all your existing migrations, look at the database, 
create necessary *migrations* to make the `humans`, `pets`, `colors` tables look like described 
(though, it will not delete existing fields, unless asked) 
and also will create or update `\App\Human`, `App\Pet`, `App\Color` Eloquent *models*
with some `$cast` attributes and methods like `pet_tags()`, `pets()`, `colors()`, `tags()`.

This defines a few models and a few relationships:

* Human has many Pets (one to many)
* Pet has many Colors (many to many with pivot table) and Tags (one to many)
* Human has many Tags through(via) Pet (has many through)

It will also create `color_pet` Many to Many pivot table with `color_id` and `pet_id` 
fields (everything is customizable).

So, now you can do:

```
$human = App\Human::create(['name' => 'Jen']);

$kitty = $human->pets()->create(['name' => 'Kitty the Cat']);

$gray = App\Color::create(['color' => 'gray']);

$kitty->colors()->attach($gray->id);

$kitty->tags()->create(['material' => 'gold']);

>>> $human->pet_tags

=> Illuminate\Database\Eloquent\Collection {
     all: [
       App\Tag {
         id: 1,
         material: "gold",
         pet_id: 1,
         human_id: 1,
         ...

```

Does the enum work?

```

$kitty->tags()->create(['material' => 'unobtanium']);
... Check violation: 7 ERROR:  new row for relation "tags" violates 
    check constraint "tags_material_check"

```

How about some JSON in models via `$casts`? Got it!

```
$kitty->update(['nicknames' => ['Kitte', 'Hey you']]);

>>> App\Pet::first()->nicknames

=> [
     "Kitte",
     "Hey you",
   ]

```

Date/time via Carbon and `$casts`? Yes!

```
$kitty->update(['born' => '2010-01-01']);

>>> App\Pet::first()->born->diffForHumans();
=> "8 years ago"

```

Want to **change** something? 

Easy! 

Change it, run `php artisan migrator` - it
will look at what needs to be done, create migrations and update your models, 
like adding an **`Unique`** index for example:

Change schema.txt, add `Unique`:

```
(skipped)

Pet
    name: string Default("Kitty"), NotNull, Unique

(skipped)
```

An unique index named `pets_name_unique_idx` (customizable) will be created (can span multiple fields too).

```
>>> $kitty = $human->pets()->create(['name' => 'Kitty the Cat']);
...

>>> $kitty = $human->pets()->create(['name' => 'Kitty the Cat']);
... Unique violation: 7 ERROR:  duplicate key value violates 
    unique constraint "pets_name_unique_idx"
``` 

# Comments

Comments start with `#`:

```
Human:
    # This is an old typo, but don't fix it
    first_nane: string
```

# Relationships

## One to many

```
Post
    comments()
    
Comment
    post()
```

Now we have `$post->comments()` and `$comment->post()`. 

The field `post_id` will be created automatically in `comments` table.

You can change the field name to `my_post_id` like this: `post() via my_post_id`.

## Different names

What if we want to have methods named `remarks()` (instead of `comments()`), but pointing to `Comment`?
Also `$comment->parent()` (instead of `post()`)? 
Easy, just add a "return type".

```
Post
    remarks(): Comment[]
    
Comment
    parent() Post
```

`Comment[]` means that this method returns `multiple` (collection) of `Comment`'s.

## One to one

```
User
    phone()
    
Phone
    user() via user_id
```

One to one is less obvious, since by default we don't know if `users` table
should have `phone_id` or `phones` table should have `user_id`.
If you try to create it without `via` - the error will try to help you
figuring out where to specify it:

```
User
    phone()
    
Phone
    user()
```

Results in:

Error:

> Model User contains a confusing One to One definition between `User.phone()` 
and `Phone.user()`. One to one requires a field in one of these tables. 
To resolve it: 
if User (usually) belongs to Phone - then add `phone() via phone_id` to User; 
otherwise if Phone (usually) belongs to User -  then add `user() via user_id` to Phone.

Which is easy to fix: add `via user_id` to `user()` definition:

```
User
    phone()
    
Phone
    user() via user_id
```

## Many to many

```
User
    roles()
    
Role
    users()
```

This will also automatically create migration to create `role_user` table 
with `role_id` and `user_id` fields.  

(see below for more complex cases, like changing the join, creating intermediary 
table, specifying pivot name, etc...)

## Has many through

So, the `Country` has many `Users`, each `User` has many `Posts`. 
How do we get all `Country`'s `Posts`?

After you do the two `has many` parts, the last part is `Country`: `posts() via User`:

```
Country
    users()
    posts() via User
    
User
    posts()
    country()
    
Post
    user()
```

Now you can call `$country->posts()`

## Polymorphic (like comments for multiple things)

`Posts` and `Videos` should have comments, but 1 comment can't belong to 
2+ `Posts` or `Post` and `Video` at the same time.

```
Post
    comments()
    
Video
    comments()
    
Comment
    commentable(): Post|Video
```

`commentable(): Post|Video` is the "polymorphic return type" 
(it returns a single `Post` or single `Video`), which will 
create `commentable_type` and `commentable_id` fields.
 
Now, we have `$post->comments()`, `$video->comments()`. 

## Polymorphic many to many (like tags for multiple things)

`Posts` and `Videos` can both have `Tags`, but same `Tag` can be for 
2+ `Posts` or `Post` and `Video` at the same time.
(Requires intermediate `Taggable` model)

```
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
```

So, now we have `$post->tags()`, `$video->tags()`, `$tag->videos()` and `$tag->posts()`.

Remark: if you need to specify a return type and via, the syntax is:

```
Tag
    posts() via Taggable: Post[]
```

# Self-reference (one to many)

`Employee` can have many `Employees`.

```
Employee
    boss() via boss_id: Employee <- Employee.employees()
    employees(): Employee[]
```

`<- Human.employees()` defines the inverse relationship.

Now we can do: `$employee->boss()` and `$employee->employees()`

*Remark:*

```
boss() via boss_id: Employee <- Employee.employees()
```

means "method `boss()`, which is defined `via` implcitly created field `boss_id`,
which returns single Employee, and inverse method is `Employee.employees()`.

```
employees(): Employee[]
```

means that method returns multiple `Employee`s (the return type here is 
not strictly necessary, since method name is plural).

# Changing namespace

By default models are created in `\App` namespace (where `User` usually is),
but you can change that:

```
namespace App\Models

Model
    name: string
```

If you don't create models in namespace starting with `\App`, you need to
specify the path too:

```
namespace MyModels src/MyModels

Model
    name: string
```
 

# Date and time

```
User
    since: datetime
    until: date
    birth_time: dateTimeTz
```

This also creates `$casts ... => 'datetime'` in model, so fields are `Carbon`

# Types with parameters

```
VeryMathematicalModel
    c: char(100) NotNull
    d: decimal(8)
    d1: decimal(8, 2)
    f: float(8, 2)
    s: string(100)
    ud: unsignedDecimal(8, 2)
    en: enum(['easy','hard'])
```

# Default values

```
User
    name: string Default("Mr.Y")
```

# JSON types

```
User
    history1: json
    history2: jsonb
    history3: collection
    history4: array
```

This also creates `$casts` in model.

# Indexes

```
Item
    title: string Index
    slug: string Unique
    
    complex1: string Index(index_named_one)
    complex2: string Index(index_named_two)
    
    unique1: string Unique(index_named_one)
    unique2: string Unique(index_named_two)
```

# Null
<a name="null"></a>

Everything is nullable by default, so to make something "not null":

```
Item
    price: integer NotNull
```

Default can be changed:

```
default not null
Item
    price: integer
```

# Rename/delete fields, indexes

```
Item
    title: string
```

change to:

```
Item
    title1: string
    RENAME FIELD title TO title1
```

Also has `DELETE FIELD field_name`, `RENAME INDEX name_from TO name_to`, `DELETE INDEX index_name`.

# Primary keys

```
User
    user_primary_key: increments PrimaryKey
```

# Guarded fields (default is unguarded)

```
User
    is_admin: boolean Guarded
    is_admin2: boolean Guarded
```

(This also creates `$guarded` in model)

Or change the default:

```
default guarded

User
    is_admin: boolean
    is_admin2: boolean
```

# Many to many (with different field names)

```
User
    user_field1: integer
    name: string
    roles(): Role[] Join(Role.role_pk = role_user.role_id AND role_user.user_id = User.user_field1)
    
Role
    role_pk: increments PrimaryKey
    name: string
    users(): User[] 
```

# Many to many (with intermediate model)

```
AssignedRole
    role_id: unsignedInteger
    user_id: unsignedInteger
    expires: integer

User
    roles(): Role[] Join(Role.id = AssignedRole.role_id AND AssignedRole.user_id = User.id)
    
Role
    users(): User[] 
```

# Many to many (pivot name, pivot timestamps)

```
User
    podcasts(): Podcast[] As("subscription"), PivotWithTimestamps
    
Podcast
    users() 
```

Now you can do `$user->podcasts[0]->subscription->created_at`.