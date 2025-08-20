<?php declare(strict_types = 1);

namespace AsisTeam\CSOBBC\Client;

use AsisTeam\CSOBBC\Entity\IFile;
use AsisTeam\CSOBBC\Entity\Upload;
use AsisTeam\CSOBBC\Enum\FileStatusEnum;
use AsisTeam\CSOBBC\Exception\LogicalException;
use AsisTeam\CSOBBC\Exception\Runtime\RequestException;
use AsisTeam\CSOBBC\Exception\Runtime\ResponseException;
use AsisTeam\CSOBBC\Exception\RuntimeException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nette\Utils\Validators;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class BCHttpClient
{

	/** @var ClientInterface */
	private $http;

	/** @var Options */
	private $options;

	public function __construct(ClientInterface $http, Options $options)
	{
		$this->http = $http;
		$this->options = $options;
	}

	public function download(string $url): string
	{
		if (!Validators::isUri($url)) {
			throw new RequestException(sprintf('Given string "%s" is not valid url', $url));
		}

		$resp = $this->send(new Request('GET', $url));
		return $resp->getBody()->getContents();
	}

	public function upload(IFile $file): IFile
	{
		if ($file->getStatus() !== FileStatusEnum::UPLOAD_AVAILABLE) {
			throw new LogicalException('Given file is not supposed to be uploaded.');
		}

		if ($file->getUploadUrl() === null) {
			throw new RequestException('File must contain valid upload url.');
		}

		if (!Validators::isUri($file->getUploadUrl())) {
			throw new RequestException(sprintf('File must contain valid upload url. "%s" given', $file->getDownloadUrl()));
		}

		// old v1 upload process
		//$hdrs = [
		//	'Content-Disposition' => sprintf('attachment; filename="%s"', $file->getFileName()),
		//	'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
		//	'Content-Length' => $file->getSize(),
		//];

		//$resp = $this->send(new Request('POST', $file->getUploadUrl(), $hdrs, new MultipartStream([$file->getContent()], $boundary)));

		$boundary = strtoupper(substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, 6));
		$multipartForm = [
			[
				'name' => $file->getFileName(),
				'contents' => $file->getContent(),
				'headers' => [
					'Content-Disposition' => sprintf('attachment; filename="%s"', $file->getFileName()),
					'Content-Type' => 'application/octet-stream',
					'Content-Length' => (string) $file->getSize(),
				]
			]
		];
		// new v2 upload process
		$options = $this->configureRequestOpts() + [
			'multipart' => $multipartForm,
			'headers' => [
				//'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
				'Content-Length' => (string) $file->getSize(),
			],
		];

		$resp = $this->request('POST', $file->getUploadUrl(), $options);

		$data = $this->extractJsonContents($resp, [200, 201]);

		if (!isset($data['newfileid'])) {
			throw new ResponseException('Upload response does not contain "NewFileId" field.');
		}

		if (!isset($data['newfilename'])) {
			throw new ResponseException('Upload response does not contain "NewFileName" field.');
		}

		$file->setUpload(new Upload((string) $data['newfileid'], (string) $data['newfilename']));

		return $file;
	}

	/**
	 * @return mixed[]
	 */
	private function configureRequestOpts(): array
	{
		return [
			'verify' => false,
			'curl' => [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2],
			'cert' => [$this->options->getCertPath(), $this->options->getCertPassphrase()],
		];
	}

	public function request(string $method, string $url, array $options = []): ResponseInterface
	{
		try {
			$resp = $this->http->request($method, $url, $options);
		} catch (Throwable $e) {
			if ($e instanceof GuzzleException) {
				throw new RequestException($e->getMessage(), $e->getCode(), $e);
			} else {
				throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
			}
		}

		return $resp;
	}

	private function send(RequestInterface $req): ResponseInterface
	{
		try {
			$resp = $this->http->send($req, $this->configureRequestOpts());
		} catch (Throwable $e) {
			if ($e instanceof GuzzleException) {
				throw new RequestException($e->getMessage(), $e->getCode(), $e);
			} else {
				throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
			}
		}

		return $resp;
	}

	/**
	 * @param int[] $validStatuses
	 * @return mixed[]
	 */
	private function extractJsonContents(ResponseInterface $resp, array $validStatuses): array
	{
		$body = $resp->getBody()->getContents();
		try {
			$data = Json::decode($body, Json::FORCE_ARRAY);
		} catch (JsonException $e) {
			throw new ResponseException(sprintf('Unable to decode contents: %s', $body));
		}

		$data = array_change_key_case($data, CASE_LOWER);
		$appStatus = $data['status'] ?? '0';


		if (!in_array((int) $appStatus, $validStatuses, true)) {

			throw new ResponseException(sprintf(
				'Server replied with "%d" statusCode, "%d" applicationCode. Message: "%s"',
				$resp->getStatusCode(),
				$appStatus,
				$data['statusmessage'] ?? 'Unknown error'
			));
		}

		return $data;
	}

}
