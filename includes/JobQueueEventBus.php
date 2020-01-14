<?php

class JobQueueEventBus extends JobQueue {
	/**
	 * Get the allowed queue orders for configuration validation
	 *
	 * @return array Subset of (random, timestamp, fifo, undefined)
	 */
	protected function supportedOrders() {
		return [ 'fifo' ];
	}

	/**
	 * Find out if delayed jobs are supported for configuration validation
	 *
	 * @return bool Whether delayed jobs are supported
	 */
	protected function supportsDelayedJobs() {
		return true;
	}

	/**
	 * Get the default queue order to use if configuration does not specify one
	 *
	 * @return string One of (random, timestamp, fifo, undefined)
	 */
	protected function optimalOrder() {
		return 'fifo';
	}

	/**
	 * @see JobQueue::isEmpty()
	 * @return bool
	 */
	protected function doIsEmpty() {
		// not implemented
		return false;
	}

	/**
	 * @see JobQueue::getSize()
	 * @return int
	 */
	protected function doGetSize() {
		// not implemented
		return 0;
	}

	/**
	 * @see JobQueue::getAcquiredCount()
	 * @return int
	 */
	protected function doGetAcquiredCount() {
		// not implemented
		return 0;
	}

	/**
	 * @param IJobSpecification[] $jobs
	 * @param int $flags
	 * @throws ConfigException
	 * @see JobQueue::batchPush()
	 */
	protected function doBatchPush( array $jobs, $flags ) {
		$streamEvents = [];
		$streamBuses = [];
		$count = 0;

		foreach ( $jobs as $job ) {
			$stream = 'mediawiki.job.' . $job->getType();
			if ( !isset( $streamBuses[$stream] ) ) {
				$streamBuses[$stream] = EventBus::getInstanceForStream( $stream );
			}
			$item = $streamBuses[$stream]->getFactory()->createJobEvent(
				$stream,
				$this->getDomain(),
				$job
			);

			if ( $item === null ) {
				continue;
			}

			$count++;
			// hash identifier => de-duplicate
			if ( isset( $item['sha1'] ) ) {
				$streamEvents[$stream][$item['sha1']] = $item;
			} else {
				$streamEvents[$stream][$item['meta']['id']] = $item;
			}
		}

		if ( !$count ) {
			// nothing to do
			return;
		}

		foreach ( array_keys( $streamEvents ) as $stream ) {
			$result = $streamBuses[$stream]->send(
				array_values( $streamEvents[$stream] ),
				EventBus::TYPE_JOB
			);

			// This means sending jobs to the $stream has failed.
			if ( is_string( $result ) ) {
				throw new JobQueueError( "Could not enqueue jobs: $result" );
			}
		}
	}

	/**
	 * @see JobQueue::pop()
	 * @return Job|bool
	 */
	protected function doPop() {
		// not implemented
		return false;
	}

	/**
	 * @see JobQueue::ack()
	 *
	 * @param RunnableJob $job
	 */
	protected function doAck( RunnableJob $job ) {
		// not implemented
	}

	/**
	 * Get an iterator to traverse over all available jobs in this queue.
	 * This does not include jobs that are currently acquired or delayed.
	 * Note: results may be stale if the queue is concurrently modified.
	 *
	 * @return Iterator
	 * @throws JobQueueError
	 */
	public function getAllQueuedJobs() {
		// not implemented
		return new ArrayIterator( [] );
	}
}
