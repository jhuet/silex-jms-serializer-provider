<?php

namespace JDesrosiers\Silex\Provider;

use Doctrine\Common\Annotations\AnnotationRegistry;
use JMS\Serializer\Naming\CamelCaseNamingStrategy;
use JMS\Serializer\Naming\IdenticalPropertyNamingStrategy;
use JMS\Serializer\Naming\PropertyNamingStrategyInterface;
use JMS\Serializer\Naming\SerializedNameAnnotationStrategy;
use JMS\Serializer\SerializerBuilder;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * JMS Serializer integration for Silex.
 */
class JmsSerializerServiceProvider implements ServiceProviderInterface
{
    /**
     * Register the jms/serializer annotations
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
        AnnotationRegistry::registerAutoloadNamespace("JMS\Serializer\Annotation", $app["serializer.srcDir"]);
    }

    /**
     * Registet the serializer and serializer.builder services
     *
     * @param Application $app
     *
     * @throws ServiceUnavailableHttpException
     */
    public function register(Application $app)
    {
        $app["serializer.namingStrategy.separator"] = null;
        $app["serializer.namingStrategy.lowerCase"] = null;

        $app["serializer.builder"] = $app->share(
            function () use ($app) {
                $serializerBuilder = SerializerBuilder::create()->setDebug($app["debug"]);

                $app->offsetExists("serializer.annotationReader") ||
                    $serializerBuilder->setAnnotationReader($app["serializer.annotationReader"]);

                $app->offsetExists("serializer.cacheDir") ||
                    $serializerBuilder->setCacheDir($app["serializer.cacheDir"]);

                $app->offsetExists("serializer.configureHandlers") ||
                    $serializerBuilder->configureHandlers($app["serializer.configureHandlers"]);

                $app->offsetExists("serializer.configureListeners") ||
                    $serializerBuilder->configureListeners($app["serializer.configureListeners"]);

                $app->offsetExists("serializer.objectConstructor") ||
                    $serializerBuilder->setObjectConstructor($app["serializer.objectConstructor"]);

                $app->offsetExists("serializer.namingStrategy") ||
                    $this->namingStrategy($app, $serializerBuilder);

                $app->offsetExists("serializer.serializationVisitors") ||
                    $this->serializationListeners($app, $serializerBuilder);

                $app->offsetExists("serializer.deserializationVisitors") ||
                    $this->deserializationListeners($app, $serializerBuilder);

                $app->offsetExists("serializer.includeInterfaceMetadata") ||
                    $serializerBuilder->includeInterfaceMetadata($app["serializer.includeInterfaceMetadata"]);

                $app->offsetExists("serializer.metadataDirs") ||
                    $serializerBuilder->setMetadataDirs($app["serializer.metadataDirs"]);

                return $serializerBuilder;
            }
        );

        $app["serializer"] = $app->share(
            function () use ($app) {
                return $app["serializer.builder"]->build();
            }
        );
    }

    protected function namingStrategy($app, $serializerBuilder)
    {
        if ($app["serializer.namingStrategy"] instanceof PropertyNamingStrategyInterface) {
            $namingStrategy = $app["serializer.namingStrategy"];
        } else {
            switch ($app["serializer.namingStrategy"]) {
                case "IdenticalProperty":
                    $namingStrategy = new IdenticalPropertyNamingStrategy();
                    break;
                case "CamelCase":
                    $namingStrategy = new CamelCaseNamingStrategy(
                        $app["serializer.namingStrategy.separator"],
                        $app["serializer.namingStrategy.lowerCase"]
                    );
                    break;
                default:
                    throw new ServiceUnavailableHttpException(
                        null,
                        "Unknown property naming strategy '{$app["serializer.namingStrategy"]}'.  " .
                        "Allowed values are 'IdenticalProperty' or 'CamelCase'"
                    );
            }

            $namingStrategy = new SerializedNameAnnotationStrategy($namingStrategy);
        }

        $serializerBuilder->setPropertyNamingStrategy($namingStrategy);
    }

    protected function serializationListeners($app, $serializerBuilder)
    {
        $serializerBuilder->addDefaultSerializationVisitors();

        foreach ($app["serializer.serializationVisitors"] as $format => $visitor) {
            $serializerBuilder->setSerializationVisitor($format, $visitor);
        }
    }

    protected function deserializationListeners($app, $serializerBuilder)
    {
        $serializerBuilder->addDefaultDeserializationVisitors();

        foreach ($app["serializer.deserializationVisitors"] as $format => $visitor) {
            $serializerBuilder->setDeserializationVisitor($format, $visitor);
        }
    }
}
