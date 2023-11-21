<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ErrorValidator
{
	private $validator;
	private $serializerInterface;

	public function __construct(ValidatorInterface $validator, SerializerInterface $serializerInterface)
	{
		$this->validator = $validator;
		$this->serializerInterface = $serializerInterface;
	}


	/**
	 * @param $errors
	 * @return array
	 */
	public function getErrors($object): JsonResponse
	{
		/*----------------------------------
		| We check if there are any errors
		-----------------------------------*/
		$errors = $this->validator->validate($object);
		if (count($errors) > 0) {
			foreach ($errors as $error) {
				$data = [
					'status' => 400,
					'message' => $error->getMessage()
				];
				return new JsonResponse($this->serializerInterface->serialize($data, 'json'), Response::HTTP_BAD_REQUEST, [], true);
			}
		} else {
			return new JsonResponse(null, Response::HTTP_OK);
		}
	}
}
