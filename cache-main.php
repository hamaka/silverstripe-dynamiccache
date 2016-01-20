<?php

// todo hier is GEEN popup

	// Include SilverStripe's core code. This is required to access cache classes
	// This is a little less lightweight than the file based cache, but still doesn't
	// involve a database hit, but allows for dependency injection
	require_once('../framework/core/Core.php');

	require_once(dirname(__FILE__) . '/../tools/code/utils/StringUtils.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/firephp/FirePHP.class.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/firephp/fb.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/Pusher.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/logger/code/LoggerPublisherInterface.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/logger/code/LoggerPublisher.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/logger/code/LoggerPublisherFirePHP.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/logger/code/LoggerPublisherScreen.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/logger/code/LoggerPublisherPusher.php');
	require_once(dirname(__FILE__) . '/../tools/code/debug/logger/code/Logger.php');

	//Logger::addPublisher( new LoggerPublisherPusher(), 'push2' ); // pushen werkt prima als je cruciale punten in flow logt naar push.. niet overladen met calls, dan heeft het een performance impact

	// start url opschonen

	/**
	 * Figure out the request URL
	 */
	global $url;

	// PHP 5.4's built-in webserver uses this
	if (php_sapi_name() == 'cli-server') {
		$url = $_SERVER['REQUEST_URI'];

		// Querystring args need to be explicitly parsed
		if(strpos($url,'?') !== false) {
			list($url, $query) = explode('?',$url,2);
			parse_str($query, $_GET);
			if ($_GET) $_REQUEST = array_merge((array)$_REQUEST, (array)$_GET);
		}

		// Pass back to the webserver for files that exist
		if(file_exists(BASE_PATH . $url) && is_file(BASE_PATH . $url)) return false;

		// Apache rewrite rules use this
	} else if (isset($_GET['url'])) {
		$url = $_GET['url'];
		// IIS includes get variables in url
		$i = strpos($url, '?');
		if($i !== false) {
			$url = substr($url, 0, $i);
		}

		// Lighttpd uses this
	} else {
		if(strpos($_SERVER['REQUEST_URI'],'?') !== false) {
			list($url, $query) = explode('?', $_SERVER['REQUEST_URI'], 2);
			parse_str($query, $_GET);
			if ($_GET) $_REQUEST = array_merge((array)$_REQUEST, (array)$_GET);
		} else {
			$url = $_SERVER["REQUEST_URI"];
		}
	}

	// Remove base folders from the URL if webroot is hosted in a subfolder
	if(strlen($url) && strlen(BASE_URL)) {
		if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
			$url = substr($url, strlen(BASE_URL));
		}
	}

// todo hier geen popup

	if(empty($url)) {
		$url = '/';
	} elseif(substr($url, 0, 1) !== '/') {
		$url = "/$url";
	}


	// eind url opschonen

	// DEBUG
	//Logger::addPublisher(new LoggerPublisherFirePHP(), 'cachetest'); ob_start();
//	Logger::log('---------------------------', 'cachetest', 'push2');
//	Logger::log('Start cache-main', 'cachetest', 'push2');
//	Logger::log($url, 'basis url', 'push2');
//	Logger::log('*hierboven is nu de basisurl*', 'basis url', 'push2');
	/**
	 * This file acts as the front end buffer between Silverstripe and the dynamic caching
	 * mechanism.
	 *
	 * Your .htaccess will need to be modified to change your framework/main.php reference
	 * to this (dynamiccache/cache-main.php). Alternatively you may set this to a custom file,
	 * set the various optional filters ($optInXXX etc) and include this file directly after,
	 * making sure to set $overrideCacheOptions directly before
	 *
	 * Caching will automatically filter via top level domain so that modules that serve
	 * domain specific content (such as subsites) will silently work.
	 *
	 * If a page should not be cached, then
	 */

	// If flush, bypass caching completely in order to delegate to Silverstripe's flush protection
	// start Hamaka hmk custom
// todo hier geen popup

	// NIET CACHEN WANNEER INGELOGD
	$bIsLoggedIn = false;
	//if(!isset($_SESSION) ) session_start(); <= NIET MEER AAN ZETTEN! ZORGT VOOR INLOGPROBLEMEN IN HET CMS (popups)
	if(isset($_SESSION) && $_SESSION != null && isset($_SESSION['loggedInAs']) && $_SESSION['loggedInAs'] != null) $bIsLoggedIn = true;
	// never use cache for logged in users
//require('../framework/main.php');
//exit;
	// start uitzondering voor sites met een front-end ledengedeelte zoals mijn-vb
	/*
	 * Pagina's met IsMembersOnly aan zouden geen probleem moeten geven omdat door bovenstaande code
	 * de ingelogde versie nooit in de cache komt en we als we ingelogd zijn ook nooit de non-members versie te zien krijgen.
	 * Dan hebben we nog alles wat in mijn-vb zit. Dat willen we uitsluiten van dynamic cache (maar kan wel weer partial gecached worden).
	 * Omdat we zonder lastige url-segment uitpluisfuncties niet kunnen opzoeken waar de huidige pagina's in de sitetree zit oid
	 * kijken we gewoon naar het eerste URL-segment na .nl Als dat /mijn is dan cachen we de huidige url niet.
	 *
	 */

	// URL OPSCHONEN
	// Remove base folders from the URL if webroot is hosted in a subfolder
	$sDynCacheTempUrl = $_SERVER['REQUEST_URI'];
	if(strlen($sDynCacheTempUrl) && strlen(BASE_URL)) {
		if (substr(strtolower($sDynCacheTempUrl), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
			$sDynCacheTempUrl = substr($sDynCacheTempUrl, strlen(BASE_URL));
		}
	}

// todo hier popup
	$bIsFormSubmit = false; // oa na een loginsubmit willen we geen caching omdat we anders doodleuk weer het inlogformulier te zien krijgen omdat het inloggen pas na cache-main.php wordt afgehandeld
	if(isset($_SESSION) && isset($_POST['formType'])) $bIsFormSubmit = true;
	if(isset($_SESSION) && $_SESSION && isset($_SESSION['MemberLoginForm'])) $bIsFormSubmit = true;

	$bPreventCaching = false;
	if($bIsLoggedIn || $bIsFormSubmit) $bPreventCaching = true;

	if($bPreventCaching)
	{
	//	Logger::log('Voorkom caching.', 'cachetest');
		//Logger::log('Niet cachen (in cache-main.php)', 'url', 'push2');
		//var_dump("NIET CACHEN");
	}
	else
	{
		//Logger::log('Caching is niet geblokkeerd in cache-main.php.', 'cachetest');
		//Logger::log('Caching is niet geblokkeerd in cache-main.php', 'url', 'push2');
		//var_dump("WEL CACHEN");
	}
//exit;
// todo hier wel popup
	// einde uitzondering

	if(isset($_GET['flush']) )
	{
		//Logger::log('Omdat we flushen wordt er niet gecached.', 'cachetest');
		//Logger::log('Omdat we flushen wordt er niet gecached.', 'cachetest', 'push2');
	}

	if(isset($_GET['flush']) || $bPreventCaching === true)
	{
		//Logger::log('Doorsturen naar main.php', 'url', 'push2');
		require('../framework/main.php');
		exit;
	}
	// end Hamaka hmk custom

	Config::inst()->update('DynamicCache', 'enabled', true); // de default

	// CONFIG SETTINGS :( - HIER OMDAT YML NIET IN ALLE SITUATIES GOED WORDT GELADEN
	if(
		strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
		||	strpos($_SERVER['HTTP_HOST'], 'westpunt') !== false
		||	strpos($_SERVER['HTTP_HOST'], 'bonbini') !== false
		||	strpos($_SERVER['HTTP_HOST'], 'mambo') !== false
		||	strpos($_SERVER['HTTP_HOST'], '192.168.1.') !== false // cache ook niet voor Hamaka
	)
	{
		// door onderstaande regels aan te zetten wordt er localhost niet gecached
		// voor nu staat die uit omdat we dan de komende week ook lokaal kunnen zien of de caching goed werkt
		//Config::inst()->update('DynamicCache', 'enabled', false);
		//Config::inst()->update('HmkMain', 'DynamicCacheEnabled', false); // nodig omdat regel 20 hierboven bij flush de flow afkapt en dan vanuit de yaml de DynamicCache -> enabled altijd op true blijft staan
	}
	else {
		Config::inst()->update('DynamicCache', 'enabled', true);
		Config::inst()->update('HmkMain', 'DynamicCacheEnabled', true); // nodig omdat regel 20 hierboven bij flush de flow afkapt en dan vanuit de yaml de DynamicCache -> enabled altijd op true blijft staan
	}

	Config::inst()->update('DynamicCache', 'cacheDuration', 0);
	Config::inst()->update('DynamicCache', 'optInHeader', null);
	Config::inst()->update('DynamicCache', 'optOutHeader', '/^X\-DynamicCache\-OptOut/');
	Config::inst()->update('DynamicCache', 'optOutHeaderString', 'X-DynamicCache-OptOut: true');
	Config::inst()->update('DynamicCache', 'responseHeader', 'X-DynamicCache');
	Config::inst()->update('DynamicCache', 'optInURL', null);
	Config::inst()->update('DynamicCache', 'optOutURL', '/(^\/admin)|(^\/dev($|\/))|(^\/Security($|\/))|(\/[A-Z])/');
	Config::inst()->update('DynamicCache', 'segmentHostname', true);
	Config::inst()->update('DynamicCache', 'enableAjax', false);
	Config::inst()->update('DynamicCache', 'cacheHeaders', '/^X\-/i');
	Config::inst()->update('DynamicCache', 'cacheBackend','DynamicCache' );

// todo hier wel popup
	require_once('code/DynamicCache.php');

	// IIS will sometimes generate this.
	if(!empty($_SERVER['HTTP_X_ORIGINAL_URL'])) {
		$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
	}
	// Activate caching here
	$instance = DynamicCache::inst();


	// NB de run functie in DynamicCache.php gaat alle settings nalopen en kan vervolgens (bijvoorbeeld op basis van de optOut settings) alsnog terugvallen op main.php van Silverstripe
	$instance->run($url);