<?php
namespace App\Exceptions;
use App\Enums\ErrorCode;
final class DomainException extends AppException {
 public function __construct(private readonly ErrorCode $codeValue) { parent::__construct(); }
 public function errorCode(): ErrorCode { return $this->codeValue; }
}
