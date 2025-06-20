<?php
namespace ScAutoParser\Core;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;

/**
 * Monolog:
 */
class Logger {

	private MonoLogger $logger;

	/**
	 * @param string $dir Директорія для збереження логів (без трейлінг-слеша).
	 */
	public function __construct( string $dir ) {

		/* Створюємо директорію, якщо треба */
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		/* Назва файлу вигляду  sc-autoparser-2025-05-14.log */
		$filename = trailingslashit( $dir ) .
		            'sc-autoparser-' . date( 'Y-m-d' ) . '.log';

		/* Ініціалізуємо Monolog */
		$this->logger = new MonoLogger( 'sc-autoparser' );
		$this->logger->pushHandler(
			new StreamHandler( $filename, MonoLogger::DEBUG, true, 0664 )
		);
	}

	/* ───────── API ───────── */

	public function info( string $message, array $context = [] ): void {
		$this->logger->info( $message, $context );
	}

	public function warning( string $message, array $context = [] ): void {
		$this->logger->warning( $message, $context );
	}

	public function error( string $message, array $context = [] ): void {
		$this->logger->error( $message, $context );
	}
}
