<?php
namespace App\Events;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
final readonly class PhoneChanged implements ShouldDispatchAfterCommit { public function __construct(public int $userId) {} }
