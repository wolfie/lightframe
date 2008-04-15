#!/usr/bin/php
<?php
/**
 * Fetch a LightFrame copy from the web.
 * 
 *  If the installed PHP supports packaging (tar.gz, bz2, zip, whatever)
 * fetch a packaged copy. Otherwise, retrieve each file separately.
 * 
 * This file shall be usable as a stand-alone file to install (and upgrade?) a 
 * fresh copy of LightFrame
 * 
 * -mirror=[url] -- which mirror to use (default to %WEBSITE%)
 * -version -- print out the version in the mirror.
 * -force=zip -- force zip
 * -force=targz -- force gzipped tarball
 * -force=bz2 -- force bzip2
 * -force=files -- force single files
 * -installpath=[path] -- install where?
 */

// TODO: figure out a distribution structure

if (!defined("STDIN"))
	die("You must run this script in the command line");


?>