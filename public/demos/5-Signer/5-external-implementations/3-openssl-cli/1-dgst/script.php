<?php

// load and register the autoload function
require_once __DIR__ . '/../../../../../../bootstrap.php';

// you need to adjust these paths to yours
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $opensslPath = 'C:\\OpenSSL\\Win64-1.1.1i\\bin\\';
} else {
    $opensslPath = '/usr/bin/';
}

// the file to sign
$fileToSign = $assetsDirectory . '/pdfs/tektown/Laboratory-Report.pdf';
// create a temporary path
$tempFile = \SetaPDF_Core_Writer_TempFile::createTempPath();

// create a writer instance
$writer = new \SetaPDF_Core_Writer_Http('signed-with-dgst.pdf');
// create the document instance
$document = \SetaPDF_Core_Document::loadByFilename($fileToSign, $writer);

// create the signer instance
$signer = new SetaPDF_Signer($document);

// let's use the PAdES modul and configure it
$module = new SetaPDF_Signer_Signature_Module_Pades();
$module->setDigest(SetaPDF_Signer_Digest::SHA_256);
$module->setCertificate('file://' . $assetsDirectory . '/certificates/setapdf-no-pw.pem');

// create a temporary version which represents the data which should get signed
$tmpDocument = $signer->preSign(new \SetaPDF_Core_Writer_File($tempFile), $module);

// get the hash data from the module
$hashData = $module->getDataToSign($tmpDocument->getHashFile());

// define some variables related to the private key
$privateKey = realpath($assetsDirectory . '/certificates/setapdf-no-pw.pem');
$privateKeyPass = '';

// create a temporary file with the data to sign
$tmpFileIn = \SetaPDF_Core_Writer_TempFile::createTempFile($hashData);
// prepare a temporary file for the final signature
$tmpFileOut = \SetaPDF_Core_Writer_TempFile::createTempPath();

// build the command
$cmd = $opensslPath . 'openssl dgst '
    . '-' . $module->getDigest() . ' '
    . '-binary '
    . "-sign " . escapeshellarg($privateKey) . ' '
    . '-passin pass:' . escapeshellarg($privateKeyPass) . ' '
    . '-out ' . escapeshellarg($tmpFileOut) . ' '
    . escapeshellarg($tmpFileIn);

// execute it
exec($cmd, $out, $retValue);

if ($retValue !== 0) {
    throw new SetaPDF_Signer_Exception(
        sprintf('An error occurs while calling OpenSSL through CLI (exit code %s).', $retValue)
    );
}

// get the signature data
$signatureValue = file_get_contents($tmpFileOut);

// pass it to the module
$module->setSignatureValue($signatureValue);

// get the final cms container
$cms = $module->getCms();
// and pass it to the main signer instance
$signer->saveSignature($tmpDocument, $cms);
