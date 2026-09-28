<?php
/**
 * FixturesService – API-Football v3
 * ---------------------------------
 * Дає TOP-матчі «сьогодні» з урахуванням локальної TZ WordPress.
 */

namespace AutoParser\Modules\Predictions;

use GuzzleHttp\Client;
use AutoParser\Core\Logger;

class FixturesService {

	/* базовий endpoint (v3) */
	private const BASE_URL = 'https://v3.football.api-sports.io';

	/* TOP-ліги: id → пріоритет */
	private const TOP_LEAGUES = array(
		2   => 1, // UCL – Champions League
		3   => 2, // UEL – Europa League
		848 => 3, // UEFA Conference League
		5   => 4, // UEFA Nations League
		9   => 5, // Copa América
		13  => 6, // Gold Cup
		14  => 7, // AFC Asian Cup
		39  => 8, // EPL – Premier League
		140 => 9, // La Liga – Spain
		135 => 10, // Serie A – Italy
		78  => 11, // Bundesliga – Germany
		61  => 12, // Ligue 1 – France
		88  => 13, // Eredivisie – Netherlands
		307 => 14, // Saudi Pro League – Saudi Arabia
		45  => 15, // FA Cup
		46  => 16, // EFL Cup (Carabao)
		143 => 17, // Copa del Rey
		137 => 18, // Coppa Italia
		82  => 19, // DFB-Pokal
		66  => 20, // Coupe de France
		94  => 21, // Португалія Primeira Liga
		144 => 22, // Бельгія Jupiler Pro League
		179 => 23, // Шотландія Premiership
		203 => 24, // Туреччина Süper Lig
		71  => 25, // Бразилія Série A
		128 => 26, // Аргентина Primera División (Liga Profesional)
		253 => 27, // США MLS
		262 => 28, // Мексика Liga MX (Clausura)
		98  => 29, // Японія J1 League
		292 => 30, // Південна Корея K-League 1

	);

	public function __construct(
		private string $api_key,
		private Client $http,
		private Logger $log,
	) {
	}

	/**
	 * @return array<array{team1:string,team2:string,datetime:string,league:string}>
	 */
	public function today_top( int $limit = 5 ): array {

		$tz_site   = wp_timezone();                 // WP тайм-зона (DateTimeZone)
		$today_loc = ( new \DateTimeImmutable( 'now', $tz_site ) )->format( 'Y-m-d' ); // «2025-05-20»
		$today_utc = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d' );

		$this->log->info( "[Fixtures] Fetch $today_utc (UTC) → відфільтровуємо $today_loc ($tz_site->getName())" );

		/* ①  HTTP-запит */
		$response = $this->get( '/fixtures', array( 'date' => $today_utc ) );

		$rows = array();
		foreach ( $response as $fx ) {

			$league_id   = (int) ( $fx['league']['id'] ?? 0 );
			$league_name = $fx['league']['name'] ?? '';

			/* тільки whitelisted ліги */
			if ( ! isset( self::TOP_LEAGUES[ $league_id ] ) ) {
				continue;
			}

			$utc_iso = $fx['fixture']['date'] ?? '';          // 2025-05-20T19:00:00+00:00
			$dt_loc  = ( new \DateTimeImmutable( $utc_iso ) )->setTimezone( $tz_site );

			/* відкидаємо, якщо після конвертації це вже не «сьогодні» */
			if ( $dt_loc->format( 'Y-m-d' ) !== $today_loc ) {
				continue;
			}

			$rows[] = array(
				'team1'    => $fx['teams']['home']['name'] ?? '',
				'team2'    => $fx['teams']['away']['name'] ?? '',
				'datetime' => $dt_loc->format( 'd.m.Y H:i' ),
				// 20.05.2025 22:00
				'league'   => $league_name,
				'__prio'   => self::TOP_LEAGUES[ $league_id ],
			);
		}

		if ( $rows === array() ) {
			throw new \RuntimeException( 'No fixtures from TOP leagues for today' );
		}

		/* спочатку пріоритет ліги, потім час */
		usort(
			$rows,
			static fn( $a, $b ) => $a['__prio'] <=> $b['__prio']
				?: strcmp( $a['datetime'], $b['datetime'] )
		);

		$rows = array_slice( $rows, 0, $limit );

		/* прибираємо технічне поле */

		return array_map(
			static fn( $r ) => array_diff_key( $r, array( '__prio' => true ) ),
			$rows
		);
	}

	/* ───────────────────────── Low-level GET ───────────────────────── */

	private function get( string $endpoint, array $query = array() ): array {

		$url = self::BASE_URL . $endpoint . '?' . http_build_query( $query );

		try {
			$resp = $this->http->get(
				$url,
				array(
					'headers' => array(
						'x-apisports-key' => $this->api_key,
						'Accept'          => 'application/json',
					),
					'timeout' => 15,
				)
			);
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'HTTP error: ' . $e->getMessage() );
		}

		$body = json_decode( (string) $resp->getBody(), true );

		if ( ! is_array( $body ) || ! isset( $body['response'] ) ) {
			throw new \RuntimeException( 'Malformed JSON from API-Football' );
		}
		if ( ! empty( $body['errors'] ) ) {
			throw new \RuntimeException(
				'API error: ' . json_encode( $body['errors'], JSON_UNESCAPED_UNICODE )
			);
		}

		return $body['response'];
	}
}
