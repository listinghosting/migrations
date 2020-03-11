<?php
namespace Whsuite\Migrations;

/**
 * WHSuite Migration System
 *
 * The WHSuite Migration System allows for migrations on both the core product
 * as well as individual addons. Currently the migration system supports running
 * individual migrations, rolling back individual migrations and resetting a
 * migration instance by rolling back all migrations.
 *
 * @package  WHSuite-Package-Migrations
 * @author  WHSuite Dev Team <info@whsuite.com>
 * @copyright  Copyright (c) 2014, Turn 24 Ltd.
 * @license http://whsuite.com/license/ The WHSuite License Agreement
 * @link http://whsuite.com
 * @since  Version 1.0
 */

use Symfony\Component\Finder\Finder;
use Illuminate\Support\Str;

class Migrations
{
    /**
     * Migrate
     *
     * Allows you to run any migrations that have not already been run for either
     * the core product or an addon.
     * @param  string $addon_slug optional addon slug to use
     */
    public function migrate($addon_slug = null, $addon_id = null)
    {
        if (! is_null($addon_slug)) {
            $table = new \AddonMigration();
            $table->where('addon', '=', $addon_slug);
            $dir = ADDON_DIR . DS . $addon_slug . DS . "migrations" . DS;

            if (empty($addon_id)) {
                $addon_id = $this->getAddonId($addon_slug);

                // check we finally do an the idea or return false
                if (empty($addon_id)) {
                    return false;
                }
            }
        } else {
            $table = new \Migration();
            $dir = STORAGE_DIR . DS . "migrations" . DS;
        }

        // If the directory doesn't exist we'll return false and continue.
        if (! is_dir($dir)) {
            return false;
        }

        $finder = new Finder();
        $finder->files()->in($dir);
        $finder->sortByName();
        $finder->files()->name('*.php');

        $addonMigrations = array();
        $migrations = array();

        try {
            $AddonMigration = \AddonMigration::get();
            if (! empty($AddonMigration)) {
                foreach ($AddonMigration as $migration) {
                    $addonMigrations[$migration->addon][$migration->migration] = $migration;
                }
            }
        } catch (\Exception $e) {
            // do nothing, tables probably don't exist
            // could be install, all migrations will just run
        }

        try {
            $AppMigration = \Migration::get();
            if (! empty($AppMigration)) {
                foreach ($AppMigration as $migration) {
                    $migrations[$migration->migration] = $migration;
                }
            }
        } catch (\Exception $e) {
            // do nothing, tables probably don't exist
            // could be install, all migrations will just run
        }

        foreach ($finder as $file) {
            $file_name = pathinfo($file->getFileName(), PATHINFO_FILENAME);

            if (! is_null($addon_slug)) {
                $migrationExists = isset($addonMigrations[$addon_slug][$file_name]);
            } else {
                $migrationExists = isset($migrations[$file_name]);
            }

            if (! $migrationExists) {
                if (! is_null($addon_slug)) {
                    $migration = \App::factory('\Addon\\'.Str::camel($addon_slug).'\Migrations\\'.$file_name);
                    $migration->up($addon_id);

                    $record = new \AddonMigration();
                    $record->addon = $addon_slug;
                } else {
                    $migration = \App::factory('\App\Storage\Migrations\\'.$file_name);
                    $migration->up();

                    $record = new \Migration();
                }

                $record->migration = $file_name;
                $record->save(); // Save the migration record to the db
            }
        }

        return true;
    }

    /**
     * Rollback
     *
     * Rolls back the latest migration applied to either the core or an addon.
     *
     * @param  string $addon_slug optional addon slug to use
     */
    public function rollback($addon_slug = null, $addon_id = null)
    {
        if (! is_null($addon_slug)) {
            $record = \AddonMigration::where('addon', '=', $addon_slug)->orderBy('migration', 'desc')->first();

            $dir = ADDON_DIR . DS . $addon_slug . DS . "migrations" . DS;

            if (! is_dir($dir)) {
                return false;
            }

            $migration = \App::factory('\Addon\\' . Str::camel($addon_slug) . '\Migrations\\' . $record->migration);

            if (empty($addon_id)) {
                $addon_id = $this->getAddonId($addon_slug);

                // check we finally do an the idea or return false
                if (empty($addon_id)) {
                    return false;
                }
            }

            $migration->down($addon_id);
        } else {
            $record = \Migration::orderBy('migration', 'desc')->first();

            $migration = \App::factory('\App\Storage\Migrations\\' . $result->migration);
            $migration->down();
        }

        try {
            $record->delete(); // Remove the migration record from the db as it's rolled back.
        } catch (\Exception $e) {
            // do nothing, tables probably don't exist
            // could be install, all migrations will just run
        }

        return true;
    }

    /**
     * Reset
     *
     * Reset an entire migration tree. This is generally only needed when performing
     * an uninstall on an addon or the core.
     *
     * @param  string $addon_slug optional addon slug to use
     */
    public function reset($addon_slug = null, $addon_id = null)
    {
        if (! is_null($addon_slug)) {
            $records = \AddonMigration::where('addon', '=', $addon_slug)->orderBy('migration', 'desc')->get();

            $dir = ADDON_DIR . DS . $addon_slug . DS . "migrations" . DS;

            if (! is_dir($dir)) {
                return false;
            }

            if (is_null($addon_id)) {
                $addon_id = $this->getAddonId($addon_slug);

                // check we finally do an the idea or return false
                if (empty($addon_id)) {
                    return false;
                }
            }

            foreach ($records as $record) {
                $migration = \App::factory('\Addon\\' . Str::camel($addon_slug) . '\Migrations\\' . $record->migration);

                // Run the down command
                if (! empty($addon_id)) {
                    $migration->down($addon_id);

                    try {
                        $record->delete(); // Remove the migration record from the db as it's rolled back.
                    } catch (\Exception $e) {
                        // do nothing, tables probably don't exist
                        // could be install, all migrations will just run
                    }
                }
            }
        } else {
            $records = \Migration::orderBy('migration', 'desc')->get();

            foreach ($records as $record) {
                $migration = \App::factory('\App\Storage\Migrations\\' . $record->migration);
                // Run the down command
                $migration->down();

                try {
                    $record->delete(); // Remove the migration record from the db as it's rolled back.
                } catch (\Exception $e) {
                    // do nothing, tables probably don't exist
                    // could be install, all migrations will just run
                }
            }
        }

        return true;
    }


    /**
     * get the addons ID given the slug
     *
     * @param   string       Addon slug
     * @return  bool|false   Addon id or false if not found
     */
    public function getAddonId($addon_slug)
    {
        $Addon = \Addon::where('directory', '=', $addon_slug)
            ->first();

        if (is_object($Addon) && ! empty($Addon->id)) {
            return $Addon->id;
        }

        return false;
    }
}
