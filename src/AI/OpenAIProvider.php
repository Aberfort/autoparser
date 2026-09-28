<?php

namespace AutoParser\AI;

use OpenAI;
use AutoParser\AI\Contract\ProviderInterface;

class OpenAIProvider implements ProviderInterface {

	public function __construct(
		private string $api_key,
		private string $model = 'gpt-4o-mini'
	) {
	}

	public function rewrite( string $text, string $prompt ): string {
		$chat = OpenAI::client( $this->api_key )->chat();
		$resp = $chat->create(
			array(
				'model'       => $this->model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => $prompt,
					),
					array(
						'role'    => 'user',
						'content' => $text,
					),
				),
				'temperature' => 0.7,
			)
		);

		return trim( $resp->choices[0]->message->content );
	}

	public function forecast( string $prompt, array $extra = array() ): string {
		$chat = OpenAI::client( $this->api_key )->chat();
		$resp = $chat->create(
			array(
				'model'       => $this->model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => 'You are an experienced sports analyst.',
					),
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.9,
			)
		);

		return trim( $resp->choices[0]->message->content );
	}
}
