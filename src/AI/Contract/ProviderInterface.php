<?php

namespace ScAutoParser\AI\Contract;

/**
 * Generic AI provider (Gemini, OpenAI, …)
 */
interface ProviderInterface {
	public function rewrite( string $text, string $prompt ): string;

	public function forecast( string $prompt, array $extra = [] ): string;
}
