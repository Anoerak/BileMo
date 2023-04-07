<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class VersioningService
{
	private $requestStack;
	private $defaultVersion;

	/**
	 * @param RequestStack $requestStack
	 * @param ParameterBagInterface $parameterBag
	 */
	public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag)
	{
		$this->requestStack = $requestStack;
		$this->defaultVersion = $parameterBag->get('default_api_version');
	}


	/**
	 * @return string
	 */
	public function getVersion(): string
	{
		$request = $this->defaultVersion;

		$request = $this->requestStack->getCurrentRequest();
		$accept = $request->headers->get('Accept');
		$entete = explode(';', $accept);

		foreach ($entete as $value) {
			if (strpos($value, 'version') !== false) {
				$version = explode('=', $value);
				$version = $version[1];
				// break;
			}
		}

		return $version;
	}
}
