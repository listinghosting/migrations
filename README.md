#Migration System

The WHSuite migration system provides a way to control both the core system migrations, as well as any addon migrations.

##Migration Locations

The addon migrations should be stored in:

**/app/addons/<addon_name>/migrations/**

The core system migrations should be stored in:

**/app/storage/migrations/**

##Migration File Naming

Whilst there's no hard fixed limit on the naming convention that must be used, file names must be in a descending order.

So you couldnt create 'migration5.php' followed by 'migration4.php' - they must be in order or they wont run.

The WHSuite Dev Team will be using the following file naming convention for all migrations. Addons for WHSuite should use the same naming convention to avoid any possible future issues.

migration_YYYY_MM_DD_HHMMSS.php

It should also be noted that the class name for the migrations should be:

Migration_YYYY_MM_DD_HHMMSS

##Class Methods

Inside each migration class you have two methods. **up()** and **down()**.

Both support full PHP, you are not restricted to just SQL queries in here, however we dont recommend any extensive file changes be performed within migrations as they are designed just for database version control.

