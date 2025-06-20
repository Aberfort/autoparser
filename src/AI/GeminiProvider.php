<?php

namespace ScAutoParser\AI;

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;
use ScAutoParser\AI\Contract\ProviderInterface;
use ScAutoParser\Core\Logger;
use RuntimeException;

class GeminiProvider implements ProviderInterface {

	public function __construct(
		private string $apiKey,
		private Logger $log,
		private string $model = 'gemini-2.0-flash'
	) {
	}

	/** Rewrite (r-e-w-r-i-t-e) */
	public function rewrite( string $text, string $prompt ): string {
		$length = mb_strlen( $text );
		$this->log->info( "Gemini rewrite input: {$length} chars" );

		$client = ( new Client( $this->apiKey ) )->withV1BetaVersion();
		$resp   = $client->generativeModel( $this->model )
		                 ->generateContent( new TextPart( trim( $prompt . "\n\n" . $text ) ) );

		$out = trim( $resp->text() );
		if ( $out === '' ) {
			throw new RuntimeException( 'Empty response from Gemini' );
		}

		return $out;
	}

	/** Forecast (html for a single match) */
	public function forecast( string $prompt, array $extra = [] ): string {
		$this->log->info( '[Gemini] forecast-prompt (' . mb_strlen( $prompt ) . ' chars)' );

		$client = ( new Client( $this->apiKey ) )->withV1BetaVersion();
		$resp   = $client->generativeModel( $this->model )
		                 ->generateContent( new TextPart( $prompt ) );

		$html = trim( $resp->text() );
		if ( $html === '' ) {
			throw new RuntimeException( 'Gemini forecast is empty.' );
		}

		return $html;
	}
}
