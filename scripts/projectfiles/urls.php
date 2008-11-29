<?php
/*
 * Cheat Sheet:
 * 
 * addURL(<URI regexp>, <view>, <arguments>); // general syntax
 * addURL(<regexp>, <app>, 'forward'); // cut the <regexp> off the URI and send it to <app>'s urls.php
 * addURL(<regexp>, <view>, array(<arguments>, 'context' => array(<context>))); // set context values manually
 * 
 */

// Remove or comment out the following line once you start working on your own project
addURL('', 'basic/show', array('template' => 'welcome.html', 'builtin' => true));

/*
 * Some built-in apps would be pre-inserted but commented out, once they get here
 */