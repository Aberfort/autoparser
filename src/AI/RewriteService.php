<?php

namespace ScAutoParser\AI;

use ScAutoParser\AI\Contract\ProviderInterface;

class RewriteService {
	public function __construct( private ProviderInterface $provider ) { }

	public function rewrite( string $content, string $prompt = '' ): string {
		return $this->provider->rewrite( $content, $prompt );
	}
}