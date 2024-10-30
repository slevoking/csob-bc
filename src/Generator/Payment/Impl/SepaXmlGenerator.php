<?php

namespace AsisTeam\CSOBBC\Generator\Payment\Impl;

use AsisTeam\CSOBBC\Entity\File;
use AsisTeam\CSOBBC\Entity\IFile;
use AsisTeam\CSOBBC\Entity\IPaymentOrder;
use AsisTeam\CSOBBC\Enum\FileFormatEnum;
use AsisTeam\CSOBBC\Enum\UploadModeEnum;
use AsisTeam\CSOBBC\Exception\Runtime\GeneratorException;
use AsisTeam\CSOBBC\Generator\Payment\IPaymentFileGenerator;
use DateTimeImmutable;

class SepaXmlGenerator implements IPaymentFileGenerator
{
	private const SEPARATOR = PHP_EOL;

	/** @var string */
	private $tmpDir;

	/** @var bool */
	private $keepTmp;

	public function __construct(string $tmpDir, bool $keepTmp = false)
	{
		$this->tmpDir = $tmpDir;
		$this->keepTmp = $keepTmp;
	}

	public function generate(array $payments, ?string $filename, string $type): IFile
	{
		$content = $payments[0];

		$file = $this->createFile($content, $filename);
		$file->setFormat(FileFormatEnum::SEPA_XML);
		$file->setSeparator(null);
		$file->setUploadMode(UploadModeEnum::ONLY_CORRECT);

		return $file;
	}


	private function createFile(string $content, ?string $filename): File
	{
		$baseName = 'payments-batch';
		if ($filename) {
			$baseName = $filename;
		}

		$file = sprintf(
			'%s/' . $baseName .'-%s-%s.xml',
			$this->tmpDir,
			(new DateTimeImmutable())->format('YmdHis'),
			substr(md5($content), 0, 6)
		);

		$bytes = file_put_contents($file, $content);
		if ($bytes === false) {
			throw new GeneratorException(sprintf('SepaXMLGenerator unable to create tmp file "%s"', $file));
		}

		return new File($file);
	}

}
