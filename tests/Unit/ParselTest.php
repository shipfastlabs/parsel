<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Exceptions\DriverNotFoundException;
use Shipfastlabs\Parsel\ParselManager;
use Shipfastlabs\Parsel\Parser;
use Shipfastlabs\Parsel\ParseRequest;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;

it('defaults to liteparse and can change the default driver', function (): void {
    $fake = Parsel::fake(['anydoc' => '# anydoc']);
    Parsel::defaultDriver('anydoc');

    expect(Parsel::file(fixture('sample.pdf'))->markdown())->toBe('# anydoc')
        ->and($fake->recordedCommands()[0][0])->toBe('anydoc');
});

it('supports an injectable manager and fluent default selection', function (): void {
    $fake = new FakeProcessRunner(['anydoc' => 'managed']);
    $manager = new ParselManager(process: $fake, binaries: ['anydoc' => 'anydoc'])->defaultDriver('anydoc');

    expect($manager->file(fixture('sample.pdf'))->markdown())->toBe('managed')
        ->and($manager->forgetDrivers())->toBe($manager);
});

it('registers and caches custom drivers', function (): void {
    $created = 0;
    $driver = new class implements Driver
    {
        public function name(): string
        {
            return 'custom';
        }

        public function validateOptions(array $options): void {}

        public function markdown(ParseRequest $request): string
        {
            return $request->source->extension;
        }
    };

    Parsel::extend('custom', function (ParselManager $manager) use (&$created, $driver): Driver {
        $created++;

        return $driver;
    });

    expect(Parsel::driver('custom')->file('report.docx')->markdown())->toBe('docx')
        ->and(Parsel::driver('custom')->file('report.pdf')->markdown())->toBe('pdf')
        ->and($created)->toBe(1);

    Parsel::fake();

    expect(Parsel::driver('custom')->file('report.txt')->markdown())->toBe('txt')
        ->and($created)->toBe(2);
});

it('replaces a resolved custom driver when extended again', function (): void {
    $manager = new ParselManager;
    $factory = fn (string $output): Closure => fn (ParselManager $manager): Driver => new readonly class($output) implements Driver
    {
        public function __construct(private string $output) {}

        public function name(): string
        {
            return 'custom';
        }

        public function validateOptions(array $options): void {}

        public function markdown(ParseRequest $request): string
        {
            return $this->output;
        }
    };

    $manager->extend('custom', $factory('first'));
    expect($manager->driver('custom')->file('a.pdf')->markdown())->toBe('first');

    $manager->extend('custom', $factory('second'));
    expect($manager->driver('custom')->file('a.pdf')->markdown())->toBe('second');
});

it('throws for an unknown driver', function (): void {
    Parsel::driver('missing');
})->throws(DriverNotFoundException::class, 'missing');

it('fakes and swaps process runners and applies default timeout', function (): void {
    Parsel::defaultTimeout(null);
    $fake = Parsel::fake(['--format text' => 'fake']);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('fake')
        ->and($fake->ranCount())->toBe(1);

    Parsel::swap(new FakeProcessRunner(['--format text' => 'swapped']));
    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('swapped');
});

it('flushes facade configuration', function (): void {
    Parsel::defaultDriver('anydoc');
    Parsel::extend('custom', fn (ParselManager $manager): Driver => throw new LogicException('Flushed drivers must not be resolved.'));
    Parsel::flush();

    $fake = Parsel::fake(['--format text' => 'ok']);
    Parsel::file(fixture('sample.pdf'))->text();

    expect($fake->recordedCommands()[0][0])->toBe('lit')
        ->and(fn (): Parser => Parsel::driver('custom'))->toThrow(DriverNotFoundException::class, 'custom');
});

it('exposes the process runner and filesystem to extension factories', function (): void {
    $fake = Parsel::fake();
    $received = null;

    Parsel::extend('probe', function (ParselManager $manager) use (&$received): Driver {
        $received = [$manager->processRunner(), $manager->filesystem()];

        return new class implements Driver
        {
            public function name(): string
            {
                return 'probe';
            }

            public function validateOptions(array $options): void {}

            public function markdown(ParseRequest $request): string
            {
                return 'ok';
            }
        };
    });

    expect(Parsel::driver('probe')->bytes('x', 'pdf')->markdown())->toBe('ok')
        ->and($received[0])->toBe($fake)
        ->and($received[1])->toBeInstanceOf(Parsel\Contracts\Filesystem::class);
});
