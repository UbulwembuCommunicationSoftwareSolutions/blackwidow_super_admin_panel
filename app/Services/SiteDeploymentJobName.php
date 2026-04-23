<?php

namespace App\Services;

/**
 * Stable keys stored in customer_subscription_deployment_jobs.job_name.
 */
final class SiteDeploymentJobName
{
    public const CREATE_SITE = 'create_site';

    public const ENSURE_FORGE_SITE = 'ensure_forge_site';

    public const SYNC_FORGE = 'sync_forge';

    public const ADD_GIT_REPO = 'add_git_repo';

    public const ADD_ENV = 'add_env';

    public const ADD_DEPLOYMENT_SCRIPT = 'add_deployment_script';

    public const ADD_SSL = 'add_ssl';

    public const DEPLOY_SITE = 'deploy_site';

    public const SEND_FORGE_COMMAND = 'send_forge_command';

    public const SEND_SYSTEM_CONFIG = 'send_system_config';
}
