<?php

declare(strict_types=1);

namespace B8im\ModuleSdk\Manifest;

use B8im\ModuleSdk\Exception\ManifestValidationException;
use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use RuntimeException;

final class ManifestLoader
{
    private string $schemaPath;

    public function __construct(
        ?string $schemaPath = null,
        private ?ManifestSemanticValidator $semanticValidator = null,
    ) {
        $this->schemaPath = $schemaPath ?? dirname(__DIR__, 2) . '/schema/module.schema.json';
        $this->semanticValidator ??= new ManifestSemanticValidator();
    }

    public function load(string $manifestPath): Manifest
    {
        if (!is_file($manifestPath) || !is_readable($manifestPath)) {
            throw new RuntimeException(sprintf('Module manifest is not readable: %s', $manifestPath));
        }

        $json = file_get_contents($manifestPath);
        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to read module manifest: %s', $manifestPath));
        }

        return $this->fromJson($json, $manifestPath);
    }

    public function fromJson(string $json, string $source = 'module.json'): Manifest
    {
        try {
            $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ManifestValidationException(
                sprintf('%s contains invalid JSON: %s', $source, $exception->getMessage()),
                [$exception->getMessage()],
            );
        }

        if (!is_object($object) || !is_array($data)) {
            throw new ManifestValidationException(
                sprintf('%s must contain a JSON object.', $source),
                ['The manifest root must be an object.'],
            );
        }

        $schema = $this->loadSchema();
        $result = (new Validator())->validate($object, $schema);

        if (!$result->isValid()) {
            $errors = (new ErrorFormatter())->formatFlat($result->error());
            throw new ManifestValidationException(
                sprintf('%s does not match the module manifest schema.', $source),
                $errors,
            );
        }

        $semanticErrors = $this->semanticValidator->validate($data);
        if ($semanticErrors !== []) {
            throw new ManifestValidationException(
                sprintf('%s failed semantic validation.', $source),
                $semanticErrors,
            );
        }

        return new Manifest($data);
    }

    private function loadSchema(): object
    {
        if (!is_file($this->schemaPath) || !is_readable($this->schemaPath)) {
            throw new RuntimeException(sprintf('Module manifest schema is not readable: %s', $this->schemaPath));
        }

        $json = file_get_contents($this->schemaPath);
        if ($json === false) {
            throw new RuntimeException(sprintf('Unable to read module manifest schema: %s', $this->schemaPath));
        }

        try {
            $schema = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('Module manifest schema contains invalid JSON: %s', $exception->getMessage()),
                previous: $exception,
            );
        }

        if (!is_object($schema)) {
            throw new RuntimeException('Module manifest schema root must be an object.');
        }

        return $schema;
    }
}
