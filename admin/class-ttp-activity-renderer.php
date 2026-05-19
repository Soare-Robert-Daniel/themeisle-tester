<?php
/**
 * Dashboard activity log renderer.
 *
 * @package Themeisle_Tester
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the recent Dashboard activity section.
 */
class TTP_Activity_Renderer {

	/**
	 * Activity store.
	 *
	 * @var TTP_Activity_Store
	 */
	private $activity_store;

	/**
	 * Constructor.
	 *
	 * @param TTP_Activity_Store $activity_store Activity store.
	 */
	public function __construct( TTP_Activity_Store $activity_store ) {
		$this->activity_store = $activity_store;
	}

	/**
	 * Render recent Dashboard activity.
	 *
	 * @return void
	 */
	public function render_activity() {
		$entries = $this->activity_store->get_all();
		?>
		<section id="ttp-activity" class="ttp-activity" aria-labelledby="ttp-activity-title">
			<div class="ttp-activity__header">
				<h2 id="ttp-activity-title"><?php esc_html_e( 'Recent activity', 'themeisle-tester' ); ?></h2>
				<p><?php esc_html_e( 'Latest Dashboard actions on this site.', 'themeisle-tester' ); ?></p>
			</div>
			<?php if ( empty( $entries ) ) : ?>
				<p class="ttp-empty"><?php esc_html_e( 'No actions recorded yet.', 'themeisle-tester' ); ?></p>
			<?php else : ?>
				<table class="ttp-activity__table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'themeisle-tester' ); ?></th>
							<th><?php esc_html_e( 'Testing Item', 'themeisle-tester' ); ?></th>
							<th><?php esc_html_e( 'Action', 'themeisle-tester' ); ?></th>
							<th><?php esc_html_e( 'Result', 'themeisle-tester' ); ?></th>
							<th><?php esc_html_e( 'Details', 'themeisle-tester' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<?php
							$result  = isset( $entry['result'] ) && is_string( $entry['result'] ) ? $entry['result'] : 'success';
							$details = isset( $entry['details'] ) && is_array( $entry['details'] ) ? $entry['details'] : array();
							$message = isset( $entry['message'] ) && is_string( $entry['message'] ) ? $entry['message'] : '';
							$time    = isset( $entry['time'] ) ? $this->format_activity_time( $entry['time'] ) : '';
							?>
							<tr>
								<td class="ttp-activity__time"><?php echo esc_html( $time ); ?></td>
								<td><?php echo esc_html( isset( $entry['item'] ) && is_string( $entry['item'] ) ? $entry['item'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $entry['action'] ) && is_string( $entry['action'] ) ? $entry['action'] : '' ); ?></td>
								<td><span class="ttp-activity__result ttp-activity__result--<?php echo esc_attr( 'error' === $result ? 'error' : 'success' ); ?>"><?php echo esc_html( 'error' === $result ? __( 'Error', 'themeisle-tester' ) : __( 'Success', 'themeisle-tester' ) ); ?></span></td>
								<td>
									<?php if ( '' !== $message ) : ?>
										<span class="ttp-activity__message"><?php echo esc_html( $message ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $details ) ) : ?>
										<ul class="ttp-activity__details">
											<?php foreach ( $details as $detail ) : ?>
												<?php if ( is_string( $detail ) && '' !== $detail ) : ?>
													<li><?php echo esc_html( $detail ); ?></li>
												<?php endif; ?>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Format an activity timestamp in the site's time format.
	 *
	 * @param mixed $time Stored MySQL datetime.
	 * @return string
	 */
	private function format_activity_time( $time ) {
		if ( ! is_string( $time ) || '' === $time ) {
			return '';
		}

		$format = get_option( 'time_format' );

		if ( ! is_string( $format ) || '' === $format ) {
			$format = 'H:i';
		}

		$formatted = mysql2date( $format, $time );

		return is_string( $formatted ) ? $formatted : '';
	}
}
