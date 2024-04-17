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

class Mt101Generator implements IPaymentFileGenerator
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

	public function generate(array $payments, string $type): IFile
	{
		$content = '';
		foreach ($payments as $payment) {
			$content .= $this->generatePaymentContent($payment) . self::SEPARATOR;
		}
		$content .= '-}';

		$file = $this->createFile($content);
		$file->setFormat(FileFormatEnum::MT101);

		$file->setSeparator(self::SEPARATOR);
		$file->setUploadMode(UploadModeEnum::ONLY_CORRECT);

		return $file;
	}

	private function generatePaymentContent(array $payment): string
	{
		$content = [];

		$content[] = '{1:F01CEKOCZPPX0000000000}{2:I101CEKOSKBXXN}{3:{113:XXXX}}{4:';
		$content[] = ':20:' . (new DateTimeImmutable('now'))->format('Ymd') . '00009828';
		$content[] = ':50H:' . $payment['sender_account_number'];
		$content[] = ':52A:CEKOCZBX';
		$content[] = ':30:' . (new DateTimeImmutable('now'))->format('Ymd');
		$content[] = ':21:' . substr(md5($payment['sender_account_number'] . $payment['receiver_account_number']), 0, 16);
		$content[] = ':23E:';
		$content[] = ':32B:' . strtoupper($payment['currency']) . str_replace('.', ',', $payment['amount']);
		$content[] = ':57A:CEKOCZPPXXX';
		$content[] = ':59A:' . $payment['receiver_account_number'];
		$content[] = ':70:' . $payment['note'];
		$content[] = ':71A:SHA';

		return implode(self::SEPARATOR, $content);
	}


	private function createFile(string $content): File
	{
		$file = sprintf(
			'%s/payments-batch-%s-%s',
			$this->tmpDir,
			(new DateTimeImmutable())->format('YmdHis'),
			substr(md5($content), 0, 6)
		);

		$bytes = file_put_contents($file, iconv('UTF-8', 'Windows-1250', $content));
		if ($bytes === false) {
			throw new GeneratorException(sprintf('MT101Generator unable to create tmp file "%s"', $file));
		}

		return new File($file);
	}

}
