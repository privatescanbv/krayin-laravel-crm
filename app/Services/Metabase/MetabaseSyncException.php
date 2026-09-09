<?php

namespace App\Services\Metabase;

use RuntimeException;

/**
 * Thrown when the sync cannot proceed safely: a missing data-source dependency in
 * the target, incompatible versions, an unresolvable card reference, etc. When
 * this is raised no writes have been made to the target (dependencies are
 * resolved in a pre-flight pass before anything is created or updated).
 */
class MetabaseSyncException extends RuntimeException {}
