<?php
/**
 * FixturesService – API-Football v3
 * ---------------------------------
 * Дає TOP-матчі «сьогодні» з урахуванням локальної TZ WordPress.
 */

namespace ScAutoParser\Fixtures;

use GuzzleHttp\Client;
use ScAutoParser\Core\Logger;

class FixturesService {

	/* базовий endpoint (v3) */
	private const BASE_URL = 'https://v3.football.api-sports.io';

	/* TOP-ліги: id → пріоритет */
	private const TOP_LEAGUES = [
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

	];

	public function __construct(
		private string $apiKey,
		private Client $http,
		private Logger $log,
	) {
	}

	/**
	 * @return array<array{team1:string,team2:string,datetime:string,league:string}>
	 */
	public function todayTop( int $limit = 5 ): array {

		$tzSite   = wp_timezone();                 // WP тайм-зона (DateTimeZone)
		$todayLoc = ( new \DateTimeImmutable( 'now', $tzSite ) )->format( 'Y-m-d' ); // «2025-05-20»
		$todayUtc = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d' );

		$this->log->info( "[Fixtures] Fetch $todayUtc (UTC) → відфільтровуємо $todayLoc ($tzSite->getName())" );

		/* ①  HTTP-запит */
		$response = $this->get( '/fixtures', [ 'date' => $todayUtc ] );

		$rows = [];
		foreach ( $response as $fx ) {

			$leagueId   = (int) ( $fx['league']['id'] ?? 0 );
			$leagueName = $fx['league']['name'] ?? '';

			/* тільки whitelisted ліги */
			if ( ! isset( self::TOP_LEAGUES[ $leagueId ] ) ) {
				continue;
			}

			$utcIso = $fx['fixture']['date'] ?? '';          // 2025-05-20T19:00:00+00:00
			$dtLoc  = ( new \DateTimeImmutable( $utcIso ) )->setTimezone( $tzSite );

			/* відкидаємо, якщо після конвертації це вже не «сьогодні» */
			if ( $dtLoc->format( 'Y-m-d' ) !== $todayLoc ) {
				continue;
			}

			$rows[] = [
				'team1'    => $fx['teams']['home']['name'] ?? '',
				'team2'    => $fx['teams']['away']['name'] ?? '',
				'datetime' => $dtLoc->format( 'd.m.Y H:i' ),
				// 20.05.2025 22:00
				'league'   => $leagueName,
				'__prio'   => self::TOP_LEAGUES[ $leagueId ],
			];
		}

		if ( $rows === [] ) {
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
			static fn( $r ) => array_diff_key( $r, [ '__prio' => true ] ),
			$rows
		);
	}

	/* ───────────────────────── Low-level GET ───────────────────────── */

	private function get( string $endpoint, array $query = [] ): array {

		$url = self::BASE_URL . $endpoint . '?' . http_build_query( $query );

		try {
			$resp = $this->http->get(
				$url,
				[
					'headers' => [
						'x-apisports-key' => $this->apiKey,
						'Accept'          => 'application/json',
					],
					'timeout' => 15,
				]
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
