<?php
	/**
	 *  +------------------------------------------------------------+
	 *  | apnscp                                                     |
	 *  +------------------------------------------------------------+
	 *  | Copyright (c) Apis Networks                                |
	 *  +------------------------------------------------------------+
	 *  | Licensed under Artistic License 2.0                        |
	 *  +------------------------------------------------------------+
	 *  | Author: Matt Saladna (msaladna@apisnetworks.com)           |
	 *  +------------------------------------------------------------+
	 */

	/**
	 *  Provides common account overview functionality, also includes invariant
	 *  server information, e.g. kernel version, IP address, PCI devices, partitions...
	 *
	 * @package core
	 */
	
	class Common_Module extends Module_Skeleton
	{
		const GLOBAL_PREFERENCES_NAME = '.global';

		/**
		 * void __construct (void)
		 *
		 * @ignore
		 */
		public function __construct()
		{
			parent::__construct();
			$this->exportedFunctions = array(
				'*'                                => PRIVILEGE_ALL,
				'get_admin_username'               => PRIVILEGE_SITE | PRIVILEGE_USER,
				'get_admin_email'                  => PRIVILEGE_USER | PRIVILEGE_SITE,
				'get_perl_modules'                 => PRIVILEGE_SITE | PRIVILEGE_USER,
				'get_web_server_name'              => PRIVILEGE_SITE | PRIVILEGE_USER,
				'get_mail_server_name'             => PRIVILEGE_SITE | PRIVILEGE_USER,
				'get_ftp_server_name'              => PRIVILEGE_SITE | PRIVILEGE_USER,
				'get_web_server_ip_addr'           => PRIVILEGE_SITE | PRIVILEGE_USER,
				'get_ip_address'                   => PRIVILEGE_SITE | PRIVILEGE_USER,
				'save_service_information_backend' => PRIVILEGE_SITE | PRIVILEGE_USER | PRIVILEGE_SERVER_EXEC,
				'get_global_preferences'           => PRIVILEGE_SITE,

				/** INFORMATION **/
				'get_current_services'             => PRIVILEGE_SITE,
				'get_new_services'                 => PRIVILEGE_SITE,
				'get_old_services'                 => PRIVILEGE_SITE,
				'get_user_preferences'             => PRIVILEGE_SITE,
				'set_user_preferences'             => PRIVILEGE_SITE
			);
		}

		/**
		 * bool service_exists(string)
		 *
		 * Checks to see if a service exists on the server.  If the service
		 * does not exist, return false, otherwise return true.
		 *
		 * @privilege PRIVILEGE_ALL
		 *
		 * @param  string $mSrvcType type of service
		 * @return bool   true if the service exists, false otherwise
		 */
		public function service_exists($mSrvcType)
		{
			return is_null(parent::get_service_value($mSrvcType, "enabled"))
				? false : true;

		}

		/**
		 * bool service_enabled(string)
		 *
		 * Checks to see if a service is enabled for a given role.  If the service
		 * is not enabled, return false, otherwise return true.
		 *
		 * @privilege PRIVILEGE_ALL
		 *
		 * @param  string $mSrvcType type of service to lookup
		 *
		 * @return bool   true if service exists and is enabled, false if it does
		 *                not exist OR apnscpException if the service does not
		 *                exist on the server.
		 *
		 */
		public function service_enabled($mSrvcType)
		{
			$srvcVal = parent::get_service_value($mSrvcType, "enabled");
			if ($srvcVal instanceof Exception)
				return $srvcVal;

			return (bool)$srvcVal;
		}

		/**
		 * string get_admin_email (void)
		 *
		 * Returns the administrative e-mail associated to an account
		 *
		 * @privilege PRIVILEGE_USER|PRIVILEGE_SITE
		 *
		 * @return string administrative e-mail address
		 */
		public function get_admin_email()
		{
			return $this->get_service_value('siteinfo', 'email');
		}

		/**
		 * mixed get_service_value (string, string)
		 *
		 * Returns the corresponding value to a service type and service name
		 * if it exists, otherwise false if it does not exist
		 *
		 * @privilege PRIVILEGE_ALL
		 *
		 * @param string $mSrvcType The type of service to lookup
		 * @param string $mSrvcName A name of a corresponding value for a named
		 *                          service in $mSrvcType
		 *
		 * @return mixed
		 */
		public function get_service_value($mSrvcType, $mSrvcName = null)
		{
			$srvcVal = parent::get_service_value($mSrvcType, $mSrvcName);
			return $srvcVal;
		}

		public function get_admin_username()
		{
			return $this->get_service_value('siteinfo', 'admin_user');
		}

		/**
		 * int get_domain_expiration(string)
		 *
		 * Retrieves the domain expiration timestamp for a given domain. Certain
		 * domains are ineligible for the lookup as the registrar blocks out
		 * expiration data.  The known TLDs are as follows:
		 * *.ws
		 * *.mx
		 * *.au
		 * *.tk
		 *
		 * @deprecated @see Dns_Module::domain_expiration()
		 *
		 * @param string $domain
		 *
		 *
		 * @return int expiration as seconds since epoch
		 *
		 */
		public function get_domain_expiration($domain = null)
		{
			deprecated_func("use DNS_Module::domain_expiration()");
			if (is_null($domain)) $domain = $this->domain;
			return $this->dns_domain_expiration($domain);
		}

		public function get_php_version()
		{
			deprecated_func('use php_version()');
			return $this->php_version();
		}

		public function get_pod($module)
		{
			deprecated_func('use perl_get_pod()');
			return $this->perl_get_pod($module);
		}

		/**
		 * @deprecated
		 * @see Auth_Module::get_last_login()
		 */
		public function get_last_login()
		{
			deprecated_func('use auth_get_last_login');
			return $this->auth_get_last_login();
		}

		/**
		 * @deprecated
		 * @see Auth_Module::get_login_history()
		 */
		public function get_login_history($limit = null)
		{
			deprecated_func('use auth_get_login_history');
			return $this->auth_get_login_history($limit);
		}

		/**
		 * array get_disk_quota()
		 *
		 * Returns the disk quota for a given account
		 *
		 * two doubles packed in an associative array with indexes
		 * "used" and "total", the difference of indexes "total" and "used" represent
		 * your free disk quota.  Depending upon the user calling it, it will
		 * either contain your total site's quota usage and limit or a user's
		 * quota and limit.  If you are calling this through SOAP, please see
		 * the Site_Module::get_disk_quota_user() function for user-specific
		 * quota retrieval.  If there is no quota -- which will not happen,
		 * but is there for backwards compatibility -- the returned value
		 * for total will be NULL.
		 *
		 * @see User_Module::get_disk_quota
		 * @return array
		 */
		public function get_disk_quota()
		{
			if ($this->permission_level & PRIVILEGE_SITE) {
				$quota = $this->site_get_account_quota();
			} else if ($this->permission_level & PRIVILEGE_USER) {
				$quota = $this->user_get_quota();
			}
			$qused = $quota['qused'];
			$qhard = $this->get_service_value('diskquota', 'enabled') ? $quota['qhard'] : 0;
			return array(
				'used'  => $qused,
				'total' => $qhard
			);
		}

		public function get_mysql_version()
		{
			deprecated_func("use sql_mysql_version()");
			return $this->sql_mysql_version();
		}

		/**
		 * array get_load (void)
		 *
		 * @privilege PRIVILEGE_ALL
		 * @return array returns an assoc array of the 1, 5, and 15 minute
		 * load averages; indicies of 1,5,15
		 */
		public function get_load()
		{
			$fp = fopen('/proc/loadavg', 'r');
			$loadData = fgets($fp);
			fclose($fp);
			$loadData = array_slice(explode(" ", $loadData), 0, 3);
			return array_combine(array(1, 5, 15), $loadData);
		}

		/**
		 * array get_services()
		 * Returns an array of supported services
		 *
		 * @privilege PRIVILEGE_ALL
		 * @return array all services and corresponding values
		 */
		public function get_services()
		{
			if (IS_CLI) return $this->_collect_services($this->permission_level);

			$svc = $this->query("common_get_services");
			return $svc;
		}


		/**
		 * array collect_services(int)
		 *
		 * Finds all services for a given username/level combination
		 *
		 * @access    private
		 * @privilege PRIVILEGE_SERVER_EXEC
		 * @return object
		 *
		 */
		private function _collect_services($mType)
		{
			$svc = array();
			$prefix = $this->domain_info_path();
			if ($mType & (PRIVILEGE_SITE | PRIVILEGE_USER)) {
				$dir = opendir($this->domain_info_path() . "/current");
				while (false !== ($cfg = readdir($dir))) {
					if ($cfg == "." || $cfg == "..") continue;
					$file = $prefix . '/new/' . $cfg . '.new';
					if (!file_exists($file))
						$file = $prefix . '/current/' . $cfg;
					$data = Util_Conf::parse_ini($file);

					if (false === $data) return error($cfg . ": parse error");
					$svc[$cfg] = $data;
				}
				closedir($dir);
			}

			return $svc;
		}

		public function get_perl_version()
		{
			deprecated_func("use perl_get_version()");
			return $this->perl_version();
		}

		/**
		 *  string get_postgresql_version()
		 *
		 *  Fetches the query SELECT version(); from PostgreSQL
		 *
		 * @cache     yes
		 * @privilege PRIVILEGE_ALL
		 *
		 * @return string version name
		 */
		public function get_postgresql_version()
		{
			deprecated_func("use sql_pgsql_version()");
			return $this->sql_pgsql_version();
		}

		/**
		 * string get_web_server_name()
		 * Returns the Web server name
		 *
		 * @privilege PRIVILEGE_SITE|PRIVILEGE_USER
		 * @return string Web server name
		 */
		public function get_web_server_name()
		{
			return $this->get_service_value("apache", "webserver");
		}

		/**
		 * string get_ftp_server_name()
		 * Returns the ftp server name
		 *
		 * @privilege PRIVILEGE_SITE|PRIVILEGE_USER
		 * @return string ftp server name
		 */
		public function get_ftp_server_name()
		{
			return $this->get_service_value("proftpd", "ftpserver");
		}


		/**
		 * string get_mail_server_name()
		 * Returns the mail server name
		 *
		 * @privilege PRIVILEGE_SITE|PRIVILEGE_USER
		 * @return string mail server name
		 */
		public function get_mail_server_name()
		{
			return $this->get_service_value("sendmail", "mailserver");
		}


		/**
		 * array get_news_by_id (int)
		 *
		 * @param integer $mID news id to fetch
		 * @return array
		 * @deprecated
		 */
		public function get_news_by_id($mID)
		{
			return deprecated_func("obsolete component");

		}

		/**
		 * array get_news(int, int, int)
		 *
		 * @param integer $mYear  the year of the news to fetch
		 * @param integer $mMonth month range: [1-12]
		 * @param integer $mDay   day   range: [1-31]
		 * @return array all news items that exit for the given date.  Indexes are
		 *                        -type: type of the news
		 *                        -date (integer): timestamp of the news in unix time (10 digit unix timestamp)
		 *                        -id (integer): news id
		 *                        -title: title of the news article
		 *                        -body: news article body
		 * @deprecated
		 */
		public function get_news($mYear, $mMonth, $mDay)
		{
			return deprecated_func("obsolete component");
		}
		/* }}} */
		/**
		 * array get_news_for_month(int, int)
		 *
		 * @param integer $mYear
		 * @param integer $mMonth
		 * @deprecated
		 */
		public function get_news_for_month($mYear, $mMonth)
		{
			return deprecated_func("obsolete component");
		}

		/**
		 * array get_news_types (void)
		 *
		 * @return array
		 * @deprecated
		 */
		public function get_news_types()
		{
			return deprecated_func("obsolete component");
		}

		/**
		 * array get_last_n_news (integer)
		 *
		 * @param integer $mNumber
		 * @return array all news items that exit for the given date.  Indexes are
		 *      -type: type of the news
		 *      -date (integer): timestamp of the news in unix time (10 digit unix timestamp)
		 *      -id (integer): news id
		 *      -title: title of the news article
		 *      -body: news article body
		 * @deprecated
		 */
		public function get_last_n_news($mNumber)
		{
			return deprecated_func("obsolete component");
		}

		/**
		 * string get_uptime([bool = false])
		 * Returns the server uptime
		 *
		 * @param bool $mPrettyPrint return data as string (true) or int (false)
		 * @privilege PRIVILEGE_ALL
		 * @return mixed server load
		 */
		public function get_uptime($mPrettyPrint = true)
		{
			$fp = fopen('/proc/uptime', 'r');
			$uptimeData = fgets($fp);
			fclose($fp);
			$arr = explode(" ", $uptimeData);
			$uptimeData = (int)array_shift($arr);

			if (!$mPrettyPrint)
				return $uptimeData;

			$uptimeStr = "";
			for ($i = 0,
			     $units = array(array(30, 'month'),
				     array(24, 'day'),
				     array(60, 'hour'),
				     array(60, 'min')),
			     $dechunkifier = 30 * 24 * 60 * 60; $i < sizeof($units); $i++) {
				$cWholeUnit = $uptimeData / $dechunkifier;
				$cPartialUnit = $uptimeData % $dechunkifier;
				if ($cWholeUnit > 1) {
					$uptimeStr .= (int)$cWholeUnit . " " . $units[$i][1] . (($cWholeUnit >= 2) ? sprintf('s') : sprintf('')) . " ";
					$uptimeData %= $dechunkifier;
				}
				$dechunkifier /= $units[$i][0];
			}
			return rtrim($uptimeStr);

		}

		/**
		 * array get_perl_modules()
		 * Returns the list of Perl modules available to a user
		 *
		 * @privilege PRIVILEGE_SITE|PRIVILEGE_USER
		 * @return array list of modules available
		 */
		public function get_perl_modules()
		{
			deprecated_func('use Perl_Module::get_modules()');
			return $this->perl_get_modules();
		}

		/**
		 * string get_web_server_ip_addr()
		 *
		 * Returns the IP address of the Web server
		 *
		 * @deprecated  @see get_ip_address()
		 * @privilege   PRIVILEGE_SITE|PRIVILEGE_USER
		 * @return string IP address of the Web server
		 */

		public function get_web_server_ip_addr()
		{
			deprecated(__FUNCTION__ . ": use get_ip_address()");
			return $this->get_ip_address();
		}

		// {{{ get_ip_address()

		/**
		 * IP address of domain
		 *
		 * @return string
		 */
		public function get_ip_address()
		{
			return ($this->get_service_value("ipinfo", "namebased") == 1) ?
				$this->get_service_value("ipinfo", "nbaddrs") :
				$this->get_service_value("ipinfo", "ipaddrs");
		}

		/**
		 *  int get_listening_ip_addr
		 *
		 * @return int primary ip address bound to the server as a long
		 */
		public function get_listening_ip_addr()
		{
			return gethostbyname($this->get_canonical_hostname());
		}

		/**
		 * string get_canonical_hostname()
		 *
		 * @return string get_canonical hostname of the server
		 */
		public function get_canonical_hostname()
		{
			if ($fp = fopen('/proc/sys/kernel/hostname', 'r')) {
				$result = trim(fgets($fp, 4096));
				fclose($fp);
			} else {
				$result = null;
			}
			return $result;
		}


		/**
		 * string get_kernel_version()
		 *
		 * @return string
		 */
		public function get_kernel_version()
		{
			return file_get_contents('/proc/sys/kernel/ostype') . ' ' . file_get_contents('/proc/sys/kernel/osrelease');
		}

		/**
		 * string get_operating_system()
		 *
		 * @return string
		 */
		public function get_operating_system()
		{
			if (file_exists('/etc/redhat-release'))
				return file_get_contents('/etc/redhat-release');
			return null;
		}

		public function get_processor_information()
		{
			$cpuinfo = file_get_contents('/proc/cpuinfo');
			$procs = array();
			$i = 0;
			foreach (explode("\n", $cpuinfo) as $line) {
				if (strstr($line, ':')) {
					list ($key, $val) = explode(":", $line);
					switch (trim($key)) {
						case 'processor':
							$key = 'count';
							$val = ++$i;
							break;
						case 'model name':
							$key = 'model';
							break;
						case 'cpu MHz':
							$key = 'speed';
							break;
						case 'cache size':
							$key = 'cache';
							$val += isset($procs[$key]) ? $procs[$key] : 0;
							break;
						case 'bogomips':
							$key = 'bogomips';
							$val += isset($procs[$key]) ? $procs[$key] : 0;
							break;
						default:
							continue;
					}
					$procs[$key] = trim($val);
				}

			}

			return $procs;
		}

		/**
		 * string list_pci_devices()
		 * The call is equivalent to /sbin/lspci
		 *
		 * @return string list of PCI devices
		 */
		public function list_pci_devices()
		{
			$data = Util_Process::exec('/sbin/lspci');
			return $data['output'];

		}

		/**
		 * Parse committed service configuration\
		 *
		 * @param string|array $svc
		 * @return array
		 */
		public function get_current_services($svc)
		{
			if (!IS_CLI) return $this->query("common_get_current_services", $svc);
			return $this->_getServices($svc, 'current');
		}

		private function _getServices($svc, $type)
		{
			$suffix = '';
			if ($type == 'new') {
				$suffix = '.new';
			}

			$svcs = (array)$svc;
			$conf = array();
			$path = $this->domain_info_path() . '/' . $type;
			foreach ($svcs as $s) {
				$file = $path . '/' . $s . $suffix;
				if (!file_exists($file)) continue;
				$conf[$s] = Util_Conf::parse_ini($file);

			}
			if (!is_array($svc)) $conf = array_pop($conf);
			return $conf;
		}

		/**
		 * Parse service configuration from journal
		 *
		 * @param string|array $svc
		 * @return array
		 */
		public function get_new_services($svc = null)
		{
			if (!IS_CLI) return $this->query("common_get_new_services", $svc);
			return $this->_getServices($svc, 'new');
		}

		public function get_old_services($svc)
		{
			if (!IS_CLI) return $this->query("common_get_old_services", $svc);
			return $this->_getServices($svc, 'old');
		}

		/**
		 * bool save_service_information_backend([bool = true])
		 *
		 * @param  bool $mSync sync configuration change to master configuration.
		 *                     If the supplied parameter is false, then the new
		 *                     configuration value will be commited to the journal
		 *                     requiring EditVirtDomain to be called
		 * @return bool
		 */
		public function save_service_information_backend($services, $journal = false)
		{
			foreach ($services as $srvc_name => $data) {
				array_unshift($data, '[DEFAULT]');
				$conf = Util_Conf::write_ini($data);
				if ($journal)
					file_put_contents($this->domain_info_path() . '/new/' . $srvc_name . '.new', $conf);
				else
					file_put_contents($this->domain_info_path() . '/current/' . $srvc_name, $conf);
			}
			return true;
		}

		public function save_service_information_old($services, $journal = false)
		{
			foreach ($services as $srvc_name => $info) {
				$data = "[DEFAULT]" . "\n";
				foreach ($info as $srvc_var => $srvc_val) {
					$data .= $srvc_var . ' = ' . (!is_array($srvc_val) ? $srvc_val : (!$srvc_val ? '[]' : '[\'' . join(array_unique($srvc_val), '\', \'') . '\']')) . "\n";
				}
				if ($journal)
					file_put_contents($this->domain_info_path() . '/new/' . $srvc_name . '.new', $data);
				else
					file_put_contents($this->domain_info_path() . '/current/' . $srvc_name, $data);
			}
			return true;
		}

		/**
		 * Set a preference to apply to all users
		 *
		 * @param mixed $pref array or string representing many or a single pref
		 * @param mixed $key  null to remove preference otherwise set single pref to this value
		 *
		 */
		public function set_global_preferences($pref, $key = null)
		{
			if (is_array($pref) && !is_null($key)) {
				return error("pref is array, second parameter must be omitted");
			} else if (is_array($pref) && isset($pref[0])) {
				return error("pref must be passed as key => value array, not scalar");
			}
		}

		public function lock_global_preferences($key)
		{

		}

		public function unlock_global_preferences($key)
		{

		}

		/**
		 * Set timezone
		 *
		 * This is an API call. Use UCard::setPref() to set tz in app
		 *
		 * @param $zone
		 * @return bool
		 */
		public function set_timezone($zone)
		{
			$zi = timezone_open($zone);
			if ($zi === false) {
				return error("invalid timezone `%s'", $zone);
			}
			date_default_timezone_set($zone);
			$prefs = $this->load_preferences();
			$prefs['timezone'] = $zone;
			// update shell prefs...
			$bashrc = $this->user_get_home() . '/.bashrc';
			if (!$this->file_file_exists($bashrc)) {
				$this->file_touch($bashrc);
			}
			// possible race condition
			$contents = $this->file_get_file_contents($bashrc);
			$contents = rtrim(preg_replace(Regex::COMMON_BASH_TZ, '', $contents)) .
				"\nTZ=\"" . $zone . "\"\nexport TZ\n";
			$this->file_put_file_contents($bashrc, $contents);
			return $this->save_preferences($prefs);
		}

		/**
		 * Load user preferences
		 *
		 * @return array
		 */
		public function load_preferences()
		{
			$cache = Cache_User::spawn();
			$key = $this->_getPreferencesKey();
			$pref = $cache->get($key);
			if (false !== $pref) {
				return $pref;
			}
			if (!IS_CLI) {
				$prefs = $this->query('common_load_preferences');
				$cache->set($key, $prefs);
				return $prefs;
			}
			$prefs = array_merge($this->get_user_preferences($this->username), $this->get_global_preferences());
			return $prefs;
		}

		private function _getPreferencesKey()
		{
			return 'userprf';
		}

		public function get_user_preferences($user)
		{
			if (!IS_CLI) {
				return $this->query('common_get_user_preferences', $user);
			}
			if ($user !== $this->username && !$this->user_exists($user)) {
				return error("cannot get preferences - user `%s' does not exist", $user);
			}
			$path = $this->domain_info_path() . '/users/' . $user;
			if (!file_exists($path)) {
				return array();
			}
			return (array)unserialize(file_get_contents($path));
		}

		public function get_global_preferences()
		{
			if (!IS_CLI) {
				return $this->query('common_get_global_preferences');
			}
			$path = $this->domain_info_path() . '/users/' . self::GLOBAL_PREFERENCES_NAME;
			if (!file_exists($path)) {
				return array();
			}
			return (array)unserialize(file_get_contents($path));
		}

		public function save_preferences(array $prefs)
		{
			if (!IS_CLI) {
				$ret = $this->query('common_save_preferences', $prefs);
				if ($ret) {
					$_SESSION['preferences'] = $prefs;
				}
				return $ret;
			}
			// make sure this gets saved
			$_SESSION['preferences'] = $prefs;
			// potential race condition
			$cache = Cache_User::spawn();
			$cache->delete($this->_getPreferencesKey());
			return $this->set_user_preferences($this->username, $prefs);
		}

		public function set_user_preferences($user, array $prefs)
		{
			if (!IS_CLI) return $this->query('common_set_user_preferences', $user, $prefs);

			if ($user !== $this->username && !$this->user_exists($user)) {
				return error("unable to save preferences, invalid user `%s' specified", $user);
			}
			$path = $this->domain_info_path() . '/users/' . $user;
			return file_put_contents($path, serialize($prefs)) !== false;
		}

		/**
		 * Get default timezone for user
		 *
		 * As with set_timezone, use UCard::getPref() in the CP
		 *
		 * @param $zone
		 * @return string
		 */
		public function get_timezone($zone)
		{
			$prefs = $this->load_preferences();
			if (!isset($prefs['timezone'])) {
				return date_default_timezone_get();
			}
			return $prefs['timezone'];
		}

		public function get_base_path()
		{
			if ($this->permission_level & (PRIVILEGE_SITE | PRIVILEGE_USER))
				return $this->domain_fs_path();
			return '';
		}

		public function _edit()
		{
			$conf_cur = Auth::profile()->conf->cur['siteinfo'];
			$conf_new = Auth::profile()->conf->new['siteinfo'];
			if ($conf_cur === $conf_new) {
				return;
			}
			// move preferences for user
			$newuser = $conf_new['admin_user'];
			$olduser = $conf_cur['admin_user'];
			if ($newuser !== $olduser) {
				$path = $this->domain_info_path() . '/users';
				if (!file_exists($path . '/' . $olduser)) {
					return;
				} else if (!file_exists($path . '/' . $newuser)) {
					rename($path . '/' . $olduser, $path . '/' . $newuser);
				} else {
					$msg = "cannot move preferences file, user preferences for `%s' exists";
					warn($msg, $newuser);
				}
			}
		}

		private function __scanServices()
		{
			$services = array();
			$dh = opendir($this->domain_info_path() . '/current/');
			while (false !== ($svc = readdir($dh))) {
				$services[] = $svc;
			}
			closedir($dh);
			return $services;
		}

		private function _getGlobalPreferencesKey()
		{
			return 'globalprf';
		}
	}

?>
