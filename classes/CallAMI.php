<?php

use PAMI\Client\Impl\ClientImpl as PamiClient;

class CallAMI
{

	private $conf;

	// Set Asterisk connection configuration
	function __construct()
	{
		$config = require __DIR__ . '/../config.php';

		if (is_array($config)) {
			$this->conf = $config['asterisk'];
		} else {
			$this->conf = false;
		}
	}

	/**
	 * Create new PamiClient 
	 *
	 * @return PamiClient
	 */
	public function NewPAMIClient()
	{
		$pamiClientOptions = $this->conf;

		if (!$pamiClientOptions) {
			return false;
		}

		$pamiClient = new PamiClient($pamiClientOptions);

		return $pamiClient;
	}

	/**
	 * Close PAMI client connection
	 *
	 * @param PamiClient $pamiClient
	 *
	 * @return mixed
	 */
	public function ClosePAMIClient($pamiClient)
	{
		return $pamiClient->close();
	}
}
