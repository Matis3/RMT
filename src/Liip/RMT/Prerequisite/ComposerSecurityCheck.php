<?php

/*
 * This file is part of the project RMT
 *
 * Copyright (c) 2014, Liip AG, http://www.liip.ch
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Liip\RMT\Prerequisite;

use Liip\RMT\Action\BaseAction;
use Liip\RMT\Context;
use Liip\RMT\Information\InformationRequest;
use Symfony\Component\Process\Process;

/**
 * Uses https://github.com/fabpot/local-php-security-checker to see if composer.lock contains insecure versions
 */
class ComposerSecurityCheck extends BaseAction
{
    const SKIP_OPTION = 'skip-composer-security-check';

    public function __construct($options)
    {
        $this->options = $options;
    }

    public function execute()
    {
        // Handle the skip option
        if (Context::get('information-collector')->getValueFor(self::SKIP_OPTION)) {
            Context::get('output')->writeln('<error>composer security check skipped</error>');

            return;
        }

        Context::get('output')->writeln('<comment>running composer security check</comment>');

        // Run the actual security check
        $process = new Process(['local-php-security-checker', '--format', 'json']);
        $process->run();

        $alerts = json_decode($process->getOutput(), true);
        if (count($alerts) === 0) {
            if (! $process->isSuccessful()) {
                Context::get('output')->writeln('<error>Error while trying to execute `local-php-security-checker` command. Are you sure the binary is installed globally in your system?</error>');
                return;
            }

            // Exit successfully if everything is fine
            $this->confirmSuccess();
            return;
        }

        // print out the advisories if available
        foreach ($alerts as $package => $alert) {
            Context::get('output')->writeln("<options=bold>{$package}</options=bold> {$alert['version']}");
            foreach ($alert['advisories'] as $data) {
                Context::get('output')->writeln('');
                Context::get('output')->writeln($data['title']);
                Context::get('output')->writeln($data['link']);
                Context::get('output')->writeln('');
            }
        }

        // throw exception to have check fail
        throw new \Exception(
            'composer.lock contains insecure packages (you can force a release with option --'.self::SKIP_OPTION.')'
        );
    }

    public function getInformationRequests()
    {
        return array(
            new InformationRequest(
                self::SKIP_OPTION,
                array(
                    'description' => 'Do not run composer security check before the release',
                    'type' => 'confirmation',
                    'interactive' => false,
                )
            ),
        );
    }
}
