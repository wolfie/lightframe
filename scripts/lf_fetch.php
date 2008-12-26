#!/usr/bin/php
<?php
/*
 * Copyright 2008 LightFrame Team
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
 * 
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License v2.0
 * @author Henrik Paul
 */

// TODO: figure out a distribution structure

if (!defined('STDIN'))
	die('You must run this script in the command line');
