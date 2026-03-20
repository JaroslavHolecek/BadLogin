# BadLogin
Simple PHP module for login mechanism.
Consists of one entity in database and PHP scripts.

## Happen
Module store data in (your) database and use `$_SESSION` variable.

## Login method
Module now support:
 - email + password
 - token link sent via email

# Showroom
For example of usage see `public` directory

# Initialization
For initialization of this module:
 - create database entity in your database. Entity is stated in `src/db/bl_create.sql`
 - create PDO database connection (see `public/db_connect.php` for example how to do this) in your skript
    - pass this PDO connection to module functions when using it
 - create copy of `.env.example` directory with `bl_config.php.example` and `db_config.php.example` files in it and remove ".example" from all names of directory as well as files
 - fill in `.env/bl_config.php` and `.env/db_config.php` with your values
 - use `$bl_config = require_once __DIR__ . 'path/to/badlogin.php';` in your files
    - call whatever function from module you want, most likely those from `src/bl_auth.php` file
