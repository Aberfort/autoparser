<?php

namespace ScAutoParser\AI;

use ScAutoParser\AI\Contract\ProviderInterface;

class PredictionService {

	public function __construct(
		private ProviderInterface $provider,
	) {
	}

	public function getForecast( string $prompt, array $vars = [] ): string {
		$filled = strtr( $prompt, $vars );

		return $this->provider->forecast( $filled, $vars );
	}
}
