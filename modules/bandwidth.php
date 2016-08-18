<?php
	/**
	 * Bandwidth statistics
	 * @package core
	 */

	class Bandwidth_Module extends Module_Skeleton {
		const BW_DIR = '/var/log/bw';
		
		public function __construct() {
			parent::__construct();
			$this->exportedFunctions = array('*' => PRIVILEGE_SITE,);
		}

		/** 
		 * Get bandwidth consumed by period
		 *  
		 * @param $grouping string group bandwidth by 'month' or 'day'
		 * @return array  
		*/
		public function get_all_composite_bandwidth_data($grouping = 'month') {
            
			if ($grouping == 'month') {
				$q = "SELECT DATE_PART('epoch',bs.begindate) as begin,
					DATE_PART('epoch',bs.enddate) as end,
					sum(bl.in_bytes) as in_bytes,
					sum(bl.out_bytes) as out_bytes
					FROM bandwidth_spans AS bs CROSS JOIN bandwidth_log AS bl
					WHERE bl.site_id=".$this->site_id."
					AND bl.ts >= bs.begindate::timestamp
					AND bl.ts <= bs.enddate
					AND bs.site_id=".$this->site_id."
					GROUP BY bs.enddate, bs.begindate
					ORDER BY bs.begindate;";
			} else if ($grouping == 'day') {
				$q = "SELECT DATE_PART('epoch',bl.ts::DATE) as begin,
					DATE_PART('epoch',bl.ts::DATE) as end,
					sum(bl.in_bytes) as in_bytes,
					sum(bl.out_bytes) as out_bytes
					FROM bandwidth_log AS bl
					WHERE bl.site_id=".$this->site_id."
					AND bl.ts >= CURRENT_TIMESTAMP::DATE - INTERVAL '90 DAY'
					AND bl.site_id=".$this->site_id."
					GROUP BY (bl.ts::DATE)
					ORDER BY bl.ts::DATE;";
			} else return error("invalid bw grouping `$grouping'");
            
            $cachekey = 'bandwidth.gacbd.' . substr($grouping,0,2);
            
            $cache = Cache_Account::spawn();
            $bw = $cache->get($cachekey);
            if ($bw !== false) {
                return $bw;
            }
			$query = $this->pgsql->query($q);
			$bandwidth = array();
			while (($bw =  $query->fetch_object()) != FALSE) {
				$bandwidth[] = array('begin'     => (int)$bw->begin,
									 'end'       => (int)$bw->end,
									 'in_bytes'  => (double)$bw->in_bytes,
									 'out_bytes' => (double)$bw->out_bytes);
			}
            $cache->set($cachekey, $bandwidth, 43200);
			return $bandwidth;
		}

		/** 
		 *  Get bandwidth ranges
		 *  Indexes:
		 *      begin: start starting rollover date
		 *      end:   ending rollover date
		 * @return array
		 */
		public function get_cycle_periods() {			
            
            $cachekey = 'bandwidth.gcp';
            $cache = Cache_Account::spawn();
            $periods = $cache->get($cachekey);
            if ($periods !== false) {
                return $periods;
            }
            
			$query = $this->pgsql->query(
				"SELECT
					EXTRACT(epoch FROM bandwidth_spans.begindate)::integer as begin,
					EXTRACT(epoch FROM bandwidth_spans.enddate)::integer   as end
				FROM
					bandwidth_spans
				WHERE
					bandwidth_spans.site_id = ".$this->site_id."
				ORDER BY
					begin ASC");
			$periods = array();
			while (($period = $query->fetch_object()) != FALSE) {
				$periods[] = array(
                    'begin' => (int)$period->begin,
                    'end' => (int)$period->end);
			}
            
            $cache->set($cachekey, $periods, 43200);
            
			return $periods;
		}

		/**
		 * Get bandwidth consumed during a time interval
		 * @param string $begin beginning date
		 * @param string $end   ending date
		 * @return array
		 */
		public function get_by_date($begin, $end = null) {
            if (!$begin) { return error("no begin period set"); }
            // there may be collisions, but sacrifice for key len
            $cachekey = 'bandwidth.gbd.' . crc32($begin.$end);
            $cache = Cache_Account::spawn();
            $services = $cache->get($cachekey);
            if ($services !== false) {
                return $services;
            }
            
			$query = $this->pgsql->query(
				"SELECT
                    name,
					sum(in_bytes)  AS in_sum,
					sum(out_bytes) AS out_sum,
					name AS svc_name,
					info AS ext_info
				 FROM
					bandwidth_log
				 JOIN
					bandwidth_services
					USING (svc_id)
				 LEFT JOIN
				 	bandwidth_extendedinfo
				 	USING (ext_id)
				 WHERE
					 bandwidth_log.site_id = ".$this->site_id."
					AND
					 bandwidth_log.ts >= ".$this->pgsql->escape_string($begin)."::abstime
					AND
						".($end ? " bandwidth_log.ts < " 
						. $this->pgsql->escape_string($end)."::abstime AND " : "")."
					 bandwidth_services.svc_id = bandwidth_log.svc_id
					GROUP BY
						name, info");
			$services = array();
			while (($service = $query->fetch_object()) != FALSE) {
				$services[] = array(
					'in'  => (double)$service->in_sum,
					'out' => (double)$service->out_sum,
					'svc_name' => $service->svc_name,
					'ext_info' => $service->ext_info
                );
			}
            $cache->set($cachekey, $services, 43200);
			return $services;
		}

        public function _delete() {
			$glob = self::BW_DIR.'/*/' . $this->site . '{,.?}';
			foreach(glob($glob) as $f) {
				unlink($f);
			}
		}

        public function _edit()
        {
            $conf_new = Auth::profile()->conf->new;
            $conf_cur = Auth::profile()->conf->cur;
            $user = array(
                'old' => $conf_cur['siteinfo']['admin_user'],
                'new' => $conf_new['siteinfo']['admin_user']
            );
            if ($user['old'] != $user['new']) {
                $this->_change_extendedinfo($user['old'], $user['new']);
            }
        }

        public function _edit_user($user, $usernew)
        {
            // update
            $this->_change_extendedinfo($user, $usernew);
            return true;
        }

        /**
         * Update extendedinfo tag for bandwidth on changes
         *
         * @param $oldinfo
         * @param $newinfo
         * @return bool
         */
        private function _change_extendedinfo($oldinfo, $newinfo)
        {
            // only worry about username changes, since domain traffic is not
            // anticipated to carry over to new user
            $db = $this->pgsql;
            $db->query("UPDATE bandwidth_extendedinfo SET info = '" .
                pg_escape_string($newinfo) . "' WHERE site_id = " . $this->site_id .
                " AND info = '" . pg_escape_string($oldinfo) . "'");
            return $db->affected_rows() > 0;
        }

	}
?>