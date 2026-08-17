<?php declare(strict_types=1);

use SanderMuller\BoostCore\Config\BoostConfig;
use SanderMuller\BoostCore\Enums\Agent;
use SanderMuller\BoostCore\Enums\Tag;
use SanderMuller\BoostCore\Skills\Remote\RemoteSkillSource;

return BoostConfig::configure()
    ->withAgents([
        Agent::CLAUDE_CODE,
        Agent::CODEX,
    ])
    ->withAllowedVendors([
        'sandermuller/package-boost-php',
        'sandermuller/boost-core',
        'sandermuller/boost-skills',
    ])
    ->withTags([
        Tag::Php,
        Tag::Github,
        'release-automation',
        'voide',
    ])
    ->withRemoteSkills([
        RemoteSkillSource::githubPath('peterfox/agent-skills', 'main', [
            'phpstan-developer' => 'phpstan-developer',
        ]),
    ]);
