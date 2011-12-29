<?php
/* A very simple class to time stuff, it just works */

Class Timer {

   private $timings;

   function __construct () {
      $this->timings = array();
   }

   public function timing_start ($name = 'default') {
      $this->timings[] = array ('name' => $name , 'start' => explode(' ', microtime()), 'stop' => NULL );
   }

   public function timing_stop ($name = 'default') {
      foreach($this->timings as $key => $timer ) {
         if(strcmp($timer['name'], $name) == 0 ) {
            $this->timings[$key]['stop'] = explode(' ', microtime());
         }
      }
   }

   public function timing_interval ($name = 'default') {
      // See if this timer exists
      $found = 0;
      //$key = NULL;

      foreach($this->timings as $skey => $timer ) {
         if(strcmp($timer['name'], $name) == 0 ) {
            //$key = $skey;
            if (!empty($timer['start'])) {
               $found = 1;
            }
            
            if (empty($timer['stop'])) {
               $stop_time = explode(' ', microtime());
            } else {
               $stop_time = $timer['stop'];
            }

            // do the big numbers first so the small ones aren't lost
            $interval = $stop_time[1] - $timer['start'][1];
            $interval += $stop_time[0] - $timer['start'][0];
         }
      }

      if ($found == 0 ) {
         return 0;
      }

      return $interval;
   }

   public function show_interval ($name = 'default') {
      echo sprintf("[%s]%s => %s\n",__METHOD__, $name, $this->timing_interval($name));
   }

   public function show_all_timers ($name = 'default') {
      foreach($this->timings as $timer ) {
        show_interval($timer['name']);
      }
   }
}
?>
