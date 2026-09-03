<?php

namespace Pastell\Service;

use Exception;
use Pastell\Helpers\ClassHelper;
use Pastell\Service\SimpleTwigRenderer\ISimpleTwigFunction;
use Pastell\Service\SimpleTwigRenderer\PastellSecurityPolicy;
use SimpleXMLElement;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityPolicy;
use DonneesFormulaire;
use Twig\TwigFilter;
use Twig\TwigFunction;
use UnrecoverableException;

class SimpleTwigRenderer
{
    /**
     * The templates are written by the users and we have no way to know what an existing installation already relies
     * on: everything Twig ships with is allowed, except what gives access to the PHP world or to other files.
     *
     * Left out on purpose:
     *  - tags "include", "extends", "embed", "use", "block": template inclusion (the loader is empty anyway)
     *  - functions "constant", "enum", "enum_cases": access to the PHP constants and classes
     *  - functions "source", "include": read any file the loader can reach
     *  - filter "invoke": calls a PHP callable
     *  - test "constant": compares a value to a PHP constant, an equality oracle on BD_PASS and the like
     */
    private const AUTHORIZED_TWIG_TAGS = [
        'apply',
        'autoescape',
        'do',
        'for',
        'from',
        'if',
        'import',
        'macro',
        'set',
        'with',
    ];
    private const AUTHORIZED_TWIG_FILTERS = [
        'abs',
        'batch',
        'capitalize',
        'column',
        'convert_encoding',
        'date',
        'date_modify',
        'default',
        'e',
        'escape',
        'filter',
        'find',
        'first',
        'format',
        'join',
        'json_encode',
        'keys',
        'last',
        'length',
        'lower',
        'map',
        'merge',
        'nl2br',
        'number_format',
        'raw',
        'reduce',
        'replace',
        'reverse',
        'round',
        'shuffle',
        'slice',
        'sort',
        'spaceless',
        'split',
        'striptags',
        'title',
        'trim',
        'upper',
        'url_encode',
    ];
    /**
     * The xpath() functions return SimpleXMLElement objects that `{{ node }}` or `|join` converts to a string.
     * @see PastellSecurityPolicy for the navigation into their child elements.
     */
    private const AUTHORIZED_TWIG_METHODS = [SimpleXMLElement::class => ['__toString']];
    private const AUTHORIZED_TWIG_PROPERTIES = [];
    private const AUTHORIZED_TWIG_FUNCTIONS = [
        'attribute',
        'cycle',
        'date',
        'max',
        'min',
        'random',
        'range',
    ];
    /**
     * The `??` operator compiles to an implicit "defined" test.
     */
    private const AUTHORIZED_TWIG_TESTS = [
        'defined',
        'divisible by',
        'empty',
        'even',
        'iterable',
        'mapping',
        'none',
        'null',
        'odd',
        'same as',
        'sequence',
        'true',
    ];

    /**
     * @param string $template_as_string
     * @param DonneesFormulaire $donneesFormulaire
     * @param array $other_metadata
     * @return string
     * @throws UnrecoverableException
     */
    public function render(
        string $template_as_string,
        DonneesFormulaire $donneesFormulaire,
        array $other_metadata = []
    ): string {
        $functionList = $this->getFunctionList($donneesFormulaire);
        $filterList = $this->getFilterList();

        $authorizedFunctions = array_merge(self::AUTHORIZED_TWIG_FUNCTIONS, array_keys($functionList));
        $authorizedFilters = array_merge(self::AUTHORIZED_TWIG_FILTERS, array_keys($filterList));

        $twigEnvironment = new Environment(new ArrayLoader(), ['autoescape' => false]);

        /*
         * The sandbox only knows about names: a function added to the environment is still refused if it is not in the
         * security policy, whatever the order in which they are registered.
         */
        $policy = new SecurityPolicy(
            self::AUTHORIZED_TWIG_TAGS,
            $authorizedFilters,
            self::AUTHORIZED_TWIG_METHODS,
            self::AUTHORIZED_TWIG_PROPERTIES,
            $authorizedFunctions,
            self::AUTHORIZED_TWIG_TESTS,
        );
        /*
         * Without strict mode, Twig 3 always accepts the "extends" and "use" tags, the "parent" and "block" functions
         * and every test, whatever the lists above say. Strict mode is the Twig 4 behaviour.
         */
        $policy->setStrict(true);
        $twigEnvironment->addExtension(new SandboxExtension(new PastellSecurityPolicy($policy), true));

        foreach ($functionList as $twigFunction) {
            $twigEnvironment->addFunction($twigFunction);
        }
        foreach ($filterList as $twigFilter) {
            $twigEnvironment->addFilter($twigFilter);
        }

        set_error_handler([$this, 'twigNoticeAsError']);
        $all_metadata = array_merge($other_metadata, $donneesFormulaire->getRawDataWithoutPassword());

        try {
            $result = $twigEnvironment
                ->createTemplate($template_as_string)
                ->render($all_metadata);
        } catch (SyntaxError $e) {
            throw new UnrecoverableException($this->getFancyErrorMessage($e), $e->getCode(), $e);
        } catch (SecurityError $e) {
            throw new UnrecoverableException(
                'Le template twig utilise un élément non autorisé par pastell : ' . $e->getRawMessage(),
                $e->getCode(),
                $e
            );
        } catch (Exception $e) {
            throw new UnrecoverableException("Erreur sur le template $template_as_string : " . $e->getMessage());
        } finally {
            restore_error_handler();
        }

        return $result;
    }

    private function getFancyErrorMessage(SyntaxError $e): string
    {
        $template = $this->getCodeForErrorMessage($e);

        $errorMessage = \sprintf(
            "Erreur de syntaxe sur le template twig ligne %d\nMessage d'erreur : %s\n\n%s",
            $e->getTemplateLine(),
            $e->getRawMessage(),
            $template
        );
        return nl2br($errorMessage);
    }

    private function getCodeForErrorMessage(SyntaxError $e): string
    {
        if ($e->getSourceContext() === null) {
            return 'Template non disponible';
        }
        $all_line = explode("\n", $e->getSourceContext()->getCode());
        foreach ($all_line as $i => $line) {
            $all_line[$i] = \sprintf('%d. %s', $i + 1, $line);
        }

        $all_line[$e->getTemplateLine() - 1] = \sprintf(
            "\n\n<b>%s</b><em>^^^ %s</em>\n\n",
            $all_line[$e->getTemplateLine() - 1],
            $e->getRawMessage()
        );
        return  implode('', $all_line);
    }

    /**
     * @param $severity
     * @param $message
     * @throws UnrecoverableException
     */
    public function twigNoticeAsError($severity, $message)
    {
        if (!(error_reporting() & $severity)) {
            return;
        }
        throw new UnrecoverableException($message);
    }

    /**
     * @return TwigFunction[] indexed by function name
     */
    private function getFunctionList(DonneesFormulaire $donneesFormulaire): array
    {
        $function_class_list = ClassHelper::findRecursive('Pastell\Service\SimpleTwigRenderer');

        $twig_function_list = [];
        foreach ($function_class_list as $function_class) {
            if (! is_subclass_of($function_class, ISimpleTwigFunction::class)) {
                continue;
            }

            $simpleTwigFunction = new $function_class();
            $twigFunction = $simpleTwigFunction->getFunction($donneesFormulaire);
            $twig_function_list[$twigFunction->getName()] = $twigFunction;
        }

        return $twig_function_list;
    }

    /**
     * @return TwigFilter[] indexed by filter name
     */
    private function getFilterList(): array
    {
        $twigFilter = new TwigFilter('ls_unique', function (array $array) {
            return array_unique($array);
        });

        return [$twigFilter->getName() => $twigFilter];
    }
}
