<?php

namespace AutoParser\AI;

use AutoParser\AI\Contract\ProviderInterface;

class ProviderFactory {

	/**
	 * @param string $code 'gemini' | 'openai'
	 */
	public static function make( string $code ): ProviderInterface {

		$opt = get_option( 'autoparser_settings', array() );

		$logger = $GLOBALS['autoparser_logger'];

		return match ( $code ) {

			/* ---------- OpenAI GPT ---------- */
			'openai' => new OpenAIProvider(
				$opt['openai_api_key'] ?? '',
				$opt['openai_model'] ?? 'gpt-4o-mini'
			),

			/* ---------- Gemini (default) ---------- */
			default => new GeminiProvider(
				$opt['gemini_api_key'] ?? '',
				$logger,
				$opt['gemini_model'] ?? 'gemini-2.0-flash'
			),
		};
	}
}
