<?php

namespace ScAutoParser\AI;

use OpenAI;
use ScAutoParser\AI\Contract\ProviderInterface;

class OpenAIProvider implements ProviderInterface {

	public function __construct(
		private string $apiKey,
		private string $model = 'gpt-4o-mini'
	) {
	}

	public function rewrite( string $text, string $prompt ): string {
		$chat = OpenAI::client( $this->apiKey )->chat();
		$resp = $chat->create( [
			'model'       => $this->model,
			'messages'    => [
				[ 'role' => 'system', 'content' => $prompt ],
				[ 'role' => 'user', 'content' => $text ],
			],
			'temperature' => 0.7,
		] );

		return trim( $resp->choices[0]->message->content );
	}

	public function forecast( string $prompt, array $extra = [] ): string {
		$chat = OpenAI::client( $this->apiKey )->chat();
		$resp = $chat->create( [
			'model'       => $this->model,
			'messages'    => [
				[
					'role'    => 'system',
					'content' => 'You are an experienced sports analyst.'
				],
				[ 'role' => 'user', 'content' => $prompt ],
			],
			'temperature' => 0.9,
		] );

		return trim( $resp->choices[0]->message->content );
	}
}
