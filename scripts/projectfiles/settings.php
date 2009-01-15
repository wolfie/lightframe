<?php
/**
 * Project-specific config file
 *
 * This file contains the necessary settings for a specific project.
 * Project wide variables, SQL settings, the stuff. This file should under
 * no circumstances be placed in the webroot or in any publicly accessible folder!
 *
 * Keep this file secure!
 *
 * This file contains all user-configurable settings LightFrame supports.
 *
 */


/*
 * Database
 */

define ('LF_SQL_RDBMS', ''); // pgsql, mysql, sqlite
define ('LF_SQL_HOST', 'localhost'); // server or file to SQL location
define ('LF_SQL_DBNAME', ''); // database name, leave blank for sqlite.
define ('LF_SQL_USER', ''); // leave blank for sqlite
define ('LF_SQL_PASS', ''); // leave blank for sqlite
define ('LF_SQL_UNIQUE_PREFIX', ''); // make the table names unique

/*
 * Paths
 */

define ('LF_APPS_PATH', LF_PROJECT_PATH.'/apps'); // the absolute path (include trailing slash) to applications reservoir
define ('LF_TEMPLATES_PATH', LF_PROJECT_PATH.'/templates'); // the absolute path (include trailing slash) to templates
define ('LF_SITE_PATH', ''); // the path (include trailing slash) to your site. Whatever comes after http://www.server.com - e.g. /~user/ or /
define ('LF_AUTOFILL_SLASH', false); // add a slash at the end of the url, if it's missing. LF_SITE_PATH needs to be filled in properly for this to work

/*
 * Apache
 */

// This will be probably deprecated in favor of a apache_get_modules() workaround check (if necessary at all)
define ('LF_APACHE_MODREWRITE', false); // Whether MOD_REWRITE is supported or not
define ('LF_DEFAULT_CHARSET', 'utf-8'); // The default charset
define ('LF_DEFAULT_CONTENT_TYPE', 'text/html; charset='.LF_DEFAULT_CHARSET); // the default content type (change to "application/xhtml+xml" for XHTML files)

/*
 * Persistence
 */

define ('LF_SESSION_ENABLE', true); // use sessions?
define ('LF_SESSION_NAME', 'LightFrame'); // the name to use in the session cookie, if sessions are used


/*
 * Security
 */

// not yet used. Might be created dynamically by lf_setup.php
#define ('LF_CRYPTO_SALT', ''); // use a unique and random string to salt password hashes
define ('LF_DEBUG', true); // turn debug on or off
define ('LF_EXPOSE_LIGHTFRAME', true); // show LightFrame in HTTP response header?
