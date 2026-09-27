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

		$provider = match ( $code ) {

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

		/**
		 * Filters the AI provider instance, letting third-party code
		 * register providers beyond the built-in Gemini/OpenAI ones.
		 *
		 * @param ProviderInterface $provider The provider that would be used.
		 * @param string            $code     The requested provider code.
		 * @param array             $settings The plugin's `autoparser_settings` option.
		 */
		return apply_filters( 'autoparser_ai_provider', $provider, $code, $opt );
	}
}
