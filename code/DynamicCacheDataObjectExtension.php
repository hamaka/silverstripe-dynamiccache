<?php

/**
 * Ensures that dataobjects are correctly flushed from the cache on save
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */
class DynamicCacheDataObjectExtension extends DataExtension {

	/**
	 * Clear the entire dynamic cache once a dataobject has been saved.
	 * Safe and dirty.
	 *
	 */
	public function onAfterWrite() {
		DynamicCache::inst()->clear();
		DynamicCacheDataObjectExtension::rebuildCache(); // plaats deze call NOOIT in code die aan wordt geroepen via het frontend van de site, alleen in CMS acties
	}

	/**
	 * Clear the entire dynamic cache once a dataobject has been deleted.
	 * Safe and dirty.
	 *
	 */
	public function onBeforeDelete() {
		DynamicCache::inst()->clear();
	}

	/**
	 * NIET aanroepen vanuit iets anders dan een CMS functie omdat je anders via cache-main.php en de cURL requests een loop kan opstarten
	 */
	public static function rebuildCache()
	{
		// Svs:
		/**
		 * Output is al geflusht in run() dus we kunnen hier naar hartelust nieuwe cachebestanden opbouwen e.d.
		 * Vraag menu op en eventueel extra pagina's. Check of we de url al in de cache hebben.
		 * De eerste 5 pagina's die we niet in de cache hebben gaan we via multithreaded curl requests aanroepen
		 * om zo sneller de hele site weer rap te krijgen.
		*/
			$oDynCache = DynamicCache::inst();

			$sHostId = Config::inst()->get('HmkMain', 'HostId');

			if($sHostId == 'LOC')	$bIsOnline = false;
			else $bIsOnline = true;

			$nMaxCurlCalls = 15;
			$sHost = $_SERVER['HTTP_HOST'];

			if($bIsOnline)
			{
				$sBaseUrl = $sHost . '/';
			}
			else
			{ // local
				$sBaseUrl = $sHost . '/fietsenvoor.nl/';
			}

			$aUrlsToCache 	= array();
		/*
			$aUrlsToCache[]	= ''; // home
			$aUrlsToCache[]	= 'the-blind-run/';
			$aUrlsToCache[]	= 'the-blind-run/de-deelnemers/';
*/
			// url al in cache?
			$aUrlsToCall = array();

			foreach($aUrlsToCache as $sUrlToCache)
			{
				$sUrlToCache = 'http://'. $sBaseUrl . $sUrlToCache;
			//	DynamicCacheDataObjectExtension::writeToLog($sUrlToCache);
				//var_dump(sizeof($aUrlsToCall));
				if(sizeof($aUrlsToCall) <= $nMaxCurlCalls)
				{
					$cache = $oDynCache->getCache();
					$sCacheKeySeed = $sUrlToCache;

		/*
		 Todo: kijken of er nog iets moet gebeuren met IE cache en meegeven client aan curl request
					if ($iPosIE = strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
						$iVersion = substr($_SERVER['HTTP_USER_AGENT'], $iPosIE + 5, 3);
						$sCacheKeySeed .= 'IE' . $iVersion;
					}
		*/

					$cacheKey = $oDynCache->getCacheKey($sCacheKeySeed);
				//	DynamicCacheDataObjectExtension::writeToLog($sCacheKeySeed);
				//	DynamicCacheDataObjectExtension::writeToLog($cacheKey);
					DynamicCache::$sUsedCacheKey = $sCacheKeySeed;

					// Check if cached value can be returned
					$cachedValue = $cache->load($cacheKey);
					//var_dump($cachedValue);
					if($cachedValue === false) $aUrlsToCall[] = $sUrlToCache;
				}
			}

		DynamicCacheDataObjectExtension::writeToLog(print_r($aUrlsToCall,true) . ' --- ');

		// Loop through the URLs, create curl-handles
		// and attach the handles to our multi-request
		foreach ($aUrlsToCall as $url)
		{
			$command = 'wget -qO- ' . $url;
			exec('nohup ' . $command . ' > /dev/null 2>&1 &');
		}
	}

	public static  function writeToLog($sToLog)
	{
/*
		// loggen naar bestand
		$logFile = dirname(__FILE__) . "/log.txt";

		touch($logFile);
		if (	($handle = fopen($logFile, 'a'))  )
		{
			fwrite($handle, $sToLog . '
		');
			fclose($handle);
		}
		else
		{
			//$errMsg[] = 'Kan het bestand niet openen voor schrijven: ' . $cacheFile.'\r\n';
			//$errRaised = true;
		}
*/

	}
}

