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
        'voice',
        Tag::Php,
        Tag::Github,
        'release-automation',
    ])
    ->withRemoteSkills([
        RemoteSkillSource::githubPath('cloudflare/security-audit-skill', 'c1c8a8c1471069fb0e188eeaff69b8e8db6564a8', [
            'security-audit' => 'skills/security-audit',
        ]),
        RemoteSkillSource::githubPath('peterfox/agent-skills', 'main', [
            'phpstan-developer' => 'phpstan-developer',
        ]),
    ]);
