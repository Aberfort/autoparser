<?php

namespace AutoParser\AI;

use AutoParser\AI\Contract\ProviderInterface;

class PredictionService {

	public function __construct(
		private ProviderInterface $provider,
	) {
	}

	public function get_forecast( string $prompt, array $vars = array() ): string {
		$filled = strtr( $prompt, $vars );

		return $this->provider->forecast( $filled, $vars );
	}
}
