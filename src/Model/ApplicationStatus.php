<?php declare( strict_types = 1 );

namespace Waves\Model;

class ApplicationStatus
{
    const SUCCEEDED_S = 'succeeded';
    const SUCCEEDED = 1;
    const SCRIPT_EXECUTION_FAILED_S = 'script_execution_failed';
    const SCRIPT_EXECUTION_FAILED = 2;
    const UNKNOWN_S = 'unknown';
    const UNKNOWN = 3;
}
