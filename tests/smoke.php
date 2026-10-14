<?php

declare(strict_types=1);

use Alkin\FormSummaryBundle\FormSummaryBundle;
use Composer\InstalledVersions;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\Type as Type;
use Symfony\Component\HttpKernel\Kernel;

$autoload = $argv[1] ?? '';
if (!is_file($autoload)) {
    fwrite(STDERR, 'Pass the installed application vendor/autoload.php path.'.PHP_EOL);
    exit(1);
}
require $autoload;

$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$check(!class_exists(\PHPUnit\Framework\TestCase::class), 'Development dependencies must not be available.');
$path = InstalledVersions::getInstallPath('alkin/form-summary-bundle');
$check(null !== $path, 'The package was not installed.');
foreach (['composer.json', 'README.md', 'LICENSE', 'src/FormSummaryBundle.php', 'templates/form_summary_layout.html.twig', 'translations/form_summary.en.php', 'translations/form_summary.bg.php'] as $file) {
    $check(is_file($path.'/'.$file), 'Missing package file: '.$file);
}
foreach (['tests', 'tools', '.github', 'phpunit.xml.dist', 'phpstan.neon.dist'] as $file) {
    $check(!file_exists($path.'/'.$file), 'Development file included in the distribution: '.$file);
}

final class InstallationSmokeKernel extends Kernel
{
    public function __construct(private readonly string $installationDirectory)
    {
        parent::__construct('prod', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new FormSummaryBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'installation-smoke-test',
                'form' => true,
                'csrf_protection' => false,
                'translator' => ['enabled' => false],
            ]);
            $container->loadFromExtension('twig', [
                'strict_variables' => true,
                'form_themes' => ['bootstrap_5_layout.html.twig'],
            ]);
            $container->setAlias('smoke.form_factory', 'form.factory')->setPublic(true);
            $container->setAlias('smoke.twig', 'twig')->setPublic(true);
        });
    }

    public function getProjectDir(): string
    {
        return $this->installationDirectory;
    }

    public function getCacheDir(): string
    {
        return $this->installationDirectory.'/var/cache/prod';
    }

    public function getLogDir(): string
    {
        return $this->installationDirectory.'/var/log';
    }
}

$kernel = new InstallationSmokeKernel(\dirname($autoload, 2));
try {
    $kernel->boot();
    $container = $kernel->getContainer();
    $view = $container->get('smoke.form_factory')->createBuilder(Type\FormType::class, [
        'name' => '<script>untrusted</script>', 'confirmed' => false, 'status' => 'ready', 'count' => 0, 'token' => 'private-token',
    ])
        ->add('name', Type\TextType::class)
        ->add('confirmed', Type\CheckboxType::class)
        ->add('status', Type\ChoiceType::class, ['choices' => ['Ready' => 'ready']])
        ->add('count', Type\IntegerType::class)
        ->add('token', Type\HiddenType::class)
        ->getForm()->createView();
    $twig = $container->get('smoke.twig');
    $template = $twig->createTemplate('{{ form_summary(form) }}');
    $html = $template->render(['form' => $view]);
    $check(str_contains($html, '&lt;script&gt;untrusted&lt;/script&gt;'), 'Text was not escaped.');
    $check(!str_contains($html, '<script>'), 'Unsafe HTML was emitted.');
    $check(!str_contains($html, 'private-token'), 'A hidden value was emitted.');
    $check(str_contains($html, '<dd>No</dd>'), 'Boolean fallback failed without a translator.');
    $check(str_contains($html, '<dd>Ready</dd>'), 'Choice labels were not rendered.');
    $check(str_contains($html, '<dd>0</dd>'), 'Zero was treated as empty.');
    $check(!$view->isRendered(), 'The summary consumed the editable form.');
    $check($html === $template->render(['form' => $view]), 'Repeated rendering changed the output.');
    $widget = $twig->createTemplate('{{ form_widget(form.name) }}')->render(['form' => $view]);
    $check(str_contains($widget, 'form-control'), 'The global editable form theme was changed.');
    echo 'Production archive smoke test passed without development dependencies.'.PHP_EOL;
} finally {
    $kernel->shutdown();
}
