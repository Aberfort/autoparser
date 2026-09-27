<?php
namespace AutoParser\Core;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

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

		/* Назва файлу вигляду  autoparser-2025-05-14.log */
		$filename = trailingslashit( $dir ) .
		            'autoparser-' . gmdate( 'Y-m-d' ) . '.log';

		/* Ініціалізуємо Monolog. Формат "час | РІВЕНЬ | повідомлення" —
		 * саме його очікує LogController::get_items() при розборі файлу. */
		$formatter = new LineFormatter( "%datetime% | %level_name% | %message%\n", 'Y-m-d H:i:s', true, true );

		$handler = new StreamHandler( $filename, MonoLogger::DEBUG, true, 0664 );
		$handler->setFormatter( $formatter );

		$this->logger = new MonoLogger( 'autoparser' );
		$this->logger->pushHandler( $handler );
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
