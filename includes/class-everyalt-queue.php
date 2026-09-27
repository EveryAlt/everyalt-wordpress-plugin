<?php
/**
 * Background generation queue.
 *
 * Each queued job is a post meta row on the attachment: _everyalt_queue_alt or _everyalt_queue_title,
 * holding the Unix time the job may next run. No custom table is needed, and deleting an attachment
 * removes its jobs with it.
 *
 * The queue is worked by two runners, both calling process() under a lock so they never overlap:
 *  - WP-Cron, every minute while jobs are waiting. This keeps going after the browser is closed.
 *  - The browser, while any EveryAlt admin page is open (REST /queue/process). WP-Cron's loopback
 *    request fails on sites behind HTTP auth or on some localhost setups; this covers them.
 *
 * Failures are retried with backoff (1, 5, then 30 minutes) before giving up. A missing API key or a
 * reached spending limit pauses the queue instead of using up retries.
 *
 * @package EveryAlt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Every_Alt_Queue {

	const TYPES          = array( 'alt', 'title' );
	const CRON_HOOK      = 'everyalt_process_queue';
	const CRON_SCHEDULE  = 'everyalt_every_minute';
	const LOCK_OPTION    = 'every_alt_queue_lock';
	const LOCK_TTL       = 120;
	const RETRY_DELAYS   = array( 60, 300, 1800 );

	/** @var Every_Alt_Admin Runs the actual generation. */
	private $admin;

	/**
	 * @param Every_Alt_Admin $admin
	 */
	public function __construct( $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Meta key holding a job's next-run time.
	 *
	 * @param string $type 'alt' or 'title'.
	 * @return string
	 */
	public static function meta_key( $type ) {
		return '_everyalt_queue_' . $type;
	}

	/**
	 * Meta key holding a job's failed attempt count.
	 *
	 * @param string $type
	 * @return string
	 */
	private static function attempts_key( $type ) {
		return '_everyalt_queue_' . $type . '_attempts';
	}

	/**
	 * Queue images for generation. Already-queued images are left as they are.
	 *
	 * @param int[]  $ids
	 * @param string $type 'alt' or 'title'.
	 * @return int Number of newly queued images.
	 */
	public function enqueue( $ids, $type ) {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return 0;
		}
		$added = 0;
		$now   = time();
		foreach ( array_unique( array_map( 'absint', (array) $ids ) ) as $id ) {
			if ( ! $id || ! wp_attachment_is_image( $id ) ) {
				continue;
			}
			if ( add_post_meta( $id, self::meta_key( $type ), $now, true ) ) {
				delete_post_meta( $id, self::attempts_key( $type ) );
				$added++;
			}
		}
		if ( $added ) {
			self::ensure_scheduled();
		}
		return $added;
	}

	/**
	 * Remove an image's job.
	 *
	 * @param int    $id
	 * @param string $type
	 */
	public static function dequeue( $id, $type ) {
		delete_post_meta( $id, self::meta_key( $type ) );
		delete_post_meta( $id, self::attempts_key( $type ) );
	}

	/**
	 * Remove every job.
	 */
	public static function clear() {
		foreach ( self::TYPES as $type ) {
			delete_post_meta_by_key( self::meta_key( $type ) );
			delete_post_meta_by_key( self::attempts_key( $type ) );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Queue counts.
	 *
	 * @return array { alt: int, title: int, due: int, total: int, running: bool, paused: string }
	 *               paused is 'budget', 'no_key', or ''.
	 */
	public static function status() {
		global $wpdb;
		$now    = time();
		$counts = array( 'alt' => 0, 'title' => 0, 'due' => 0 );
		foreach ( self::TYPES as $type ) {
			$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(*) AS total, SUM( CAST( meta_value AS UNSIGNED ) <= %d ) AS due FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$now,
				self::meta_key( $type )
			) );
			$counts[ $type ]  = (int) $row->total;
			$counts['due']   += (int) $row->due;
		}
		$counts['total']   = $counts['alt'] + $counts['title'];
		$counts['running'] = self::is_locked();
		$counts['paused']  = '';
		if ( $counts['total'] ) {
			$model = Every_Alt_Providers::selected_model();
			if ( Every_Alt_Providers::get_key( $model['provider'] ) === '' ) {
				$counts['paused'] = 'no_key';
			} elseif ( Every_Alt_Usage::budget_reached() ) {
				$counts['paused'] = 'budget';
			}
		}
		return $counts;
	}

	/**
	 * Work through due jobs until the time limit, alt text first, oldest first.
	 *
	 * @param int $time_limit Seconds. No new job is started after this; a job in progress finishes.
	 * @return array { locked: bool, results: array[], status: array }
	 */
	public function process( $time_limit ) {
		if ( ! self::acquire_lock() ) {
			return array( 'locked' => true, 'results' => array(), 'status' => self::status() );
		}
		$started = time();
		$results = array();
		try {
			while ( time() - $started < $time_limit ) {
				$job = self::next_due_job();
				if ( ! $job ) {
					break;
				}
				self::refresh_lock();
				$result    = $this->run_job( $job['id'], $job['type'] );
				$results[] = $result;
				if ( $result['paused'] ) {
					break;
				}
			}
		} finally {
			self::release_lock();
		}
		$status = self::status();
		if ( ! $status['total'] ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
		return array( 'locked' => false, 'results' => $results, 'status' => $status );
	}

	/**
	 * WP-Cron callback.
	 */
	public function process_from_cron() {
		// Leave headroom under typical 60s PHP limits; a request in progress can take up to 60s itself.
		$this->process( 40 );
	}

	/**
	 * Run one job and update the queue according to the outcome.
	 *
	 * @param int    $id
	 * @param string $type
	 * @return array { media_id, type, success, text, decorative, message, paused }
	 */
	private function run_job( $id, $type ) {
		if ( ! get_post( $id ) ) {
			self::dequeue( $id, $type );
			return array( 'media_id' => $id, 'type' => $type, 'success' => false, 'text' => '', 'decorative' => false, 'message' => __( 'Image no longer exists.', 'everyalt' ), 'paused' => '' );
		}

		$result = $type === 'alt'
			? $this->admin->every_alt_auto_add_image_alt_text( $id, true )
			: $this->admin->every_alt_auto_add_image_title( $id, true );

		$out = array(
			'media_id'   => $id,
			'type'       => $type,
			'success'    => (bool) $result,
			'text'       => $result ? (string) ( $type === 'alt' ? $result->alt : $result->title ) : '',
			'decorative' => $result && ! empty( $result->decorative ),
			'message'    => $result ? '' : $this->admin->every_alt_get_last_log_message_for_attachment( $id ),
			'paused'     => '',
		);

		if ( $result ) {
			self::dequeue( $id, $type );
			return $out;
		}

		switch ( $this->admin->every_alt_last_failure() ) {
			case 'no_key':
			case 'budget':
				// Nothing wrong with this image: leave it queued and stop until the situation changes.
				$out['paused'] = $this->admin->every_alt_last_failure();
				break;
			case 'invalid_image':
				self::dequeue( $id, $type );
				break;
			default:
				$attempts = (int) get_post_meta( $id, self::attempts_key( $type ), true );
				if ( $attempts >= count( self::RETRY_DELAYS ) ) {
					self::dequeue( $id, $type );
					$out['message'] .= ' ' . __( '(Gave up after several attempts.)', 'everyalt' );
				} else {
					update_post_meta( $id, self::attempts_key( $type ), $attempts + 1 );
					update_post_meta( $id, self::meta_key( $type ), time() + self::RETRY_DELAYS[ $attempts ] );
					$out['message'] .= ' ' . __( '(Will retry automatically.)', 'everyalt' );
				}
		}
		return $out;
	}

	/**
	 * Oldest due job, alt text before titles.
	 *
	 * @return array|null { id: int, type: string }
	 */
	private static function next_due_job() {
		global $wpdb;
		foreach ( self::TYPES as $type ) {
			$id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND CAST( meta_value AS UNSIGNED ) <= %d ORDER BY CAST( meta_value AS UNSIGNED ) ASC, post_id ASC LIMIT 1",
				self::meta_key( $type ),
				time()
			) );
			if ( $id ) {
				return array( 'id' => (int) $id, 'type' => $type );
			}
		}
		return null;
	}

	/**
	 * Filter: cron_schedules. Adds a one-minute interval.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) {
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => 60,
			'display'  => __( 'Every minute (EveryAlt queue)', 'everyalt' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the cron runner if it isn't already, and nudge WP-Cron to start now.
	 */
	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_SCHEDULE, self::CRON_HOOK );
		}
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Take the processing lock. Uses a direct INSERT IGNORE so only one runner can win, even with a
	 * persistent object cache (add_option() checks the cache first and upserts). Stale locks from a
	 * crashed run expire after LOCK_TTL seconds.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		global $wpdb;
		$insert = function () use ( $wpdb ) {
			return (bool) $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'no' )",
				self::LOCK_OPTION,
				time()
			) );
		};
		if ( $insert() ) {
			return true;
		}
		$locked_at = self::locked_at();
		if ( $locked_at && time() - $locked_at > self::LOCK_TTL ) {
			self::release_lock();
			return $insert();
		}
		return false;
	}

	private static function refresh_lock() {
		global $wpdb;
		$wpdb->update( $wpdb->options, array( 'option_value' => time() ), array( 'option_name' => self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	private static function release_lock() {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Lock timestamp read straight from the database (bypassing the options cache), or 0.
	 *
	 * @return int
	 */
	private static function locked_at() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return bool
	 */
	private static function is_locked() {
		$locked_at = self::locked_at();
		return $locked_at && time() - $locked_at <= self::LOCK_TTL;
	}
}
