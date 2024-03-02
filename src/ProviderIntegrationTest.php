<?php

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\IntegrationTest;

use Exception;
use Geocoder\Collection;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\QuotaExceeded;
use Geocoder\Location;
use Geocoder\Model\AdminLevelCollection;
use Geocoder\Model\Bounds;
use Geocoder\Model\Coordinates;
use Geocoder\Model\Country;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Http\Discovery\Psr18ClientDiscovery;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
abstract class ProviderIntegrationTest extends TestCase
{
    /**
     * @var array<string,string> with functionName => reason
     */
    protected array $skippedTests = [];

    protected bool $testAddress = true;
    protected bool $testReverse = true;
    protected bool $testIpv4 = true;
    protected bool $testIpv6 = true;
    protected bool $testHttpProvider = true;

    /**
     * @return Provider that is used in the tests.
     */
    abstract protected function createProvider(ClientInterface $httpClient): Provider;

    /**
     * @return string the directory where cached responses are stored
     */
    abstract protected function getCacheDir(): string;

    /**
     * @return string the API key or substring to be removed from cache.
     */
    abstract protected function getApiKey(): string;

    /**
     * @return ClientInterface&MockObject
     */
    private function getHttpClient(ResponseInterface $response)
    {
        $client = $this->getMockForAbstractClass(ClientInterface::class);

        $client
            ->expects($this->any())
            ->method('sendRequest')
            ->willReturn($response);

        return $client;
    }

    /**
     * This client will make real request if cache was not found.
     */
    private function getCachedHttpClient(): CachedResponseClient
    {
        try {
            $client = Psr18ClientDiscovery::find();
        } catch (\Http\Discovery\Exception\NotFoundException $e) {
            $client = $this->getMockForAbstractClass(ClientInterface::class);

            $client
                ->expects($this->any())
                ->method('sendRequest')
                ->willThrowException($e);
        }

        return new CachedResponseClient($client, $this->getCacheDir(), $this->getApiKey());
    }

    public function testGeocodeQuery(): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }
        if (!$this->testAddress) {
            $this->markTestSkipped('Geocoding address is not supported by this provider');
        }

        $provider = $this->createProvider($this->getCachedHttpClient());
        $query = GeocodeQuery::create('10 Downing St, London, UK')->withLocale('en');
        $result = $provider->geocodeQuery($query);
        $this->assertWellFormattedResult($result);

        $location = $result->first();
        /** @var Coordinates|null $coordinates */
        $coordinates = $location->getCoordinates();
        $this->assertNotNull($coordinates, 'Coordinates should not be null');
        $this->assertEqualsWithDelta(51.5033, $coordinates->getLatitude(), 0.1, 'Latitude should be in London');
        $this->assertEqualsWithDelta(-0.1276, $coordinates->getLongitude(), 0.1, 'Longitude should be in London');

        if (null !== ($streetName = $location->getStreetName())) {
            $this->assertStringContainsString('Downing', $streetName, 'Street name should contain "Downing St"');
        }
        if (null !== ($streetNumber = $location->getStreetNumber())) {
            $this->assertStringContainsString('10', (string) $streetNumber, 'Street number should contain "10"');
        }
    }

    public function testGeocodeQueryWithNoResults(): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }
        if (!$this->testAddress) {
            $this->markTestSkipped('Geocoding address is not supported by this provider');
        }

        $provider = $this->createProvider($this->getCachedHttpClient());
        $query = GeocodeQuery::create('jsajhgsdkfjhsfkjhaldkadjaslgldasd')->withLocale('en');
        $result = $provider->geocodeQuery($query);
        $this->assertWellFormattedResult($result);
        $this->assertEquals(0, $result->count());
    }

    public function testReverseQuery(): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }
        if (!$this->testReverse) {
            $this->markTestSkipped('Reverse geocoding address is not supported by this provider');
        }

        $provider = $this->createProvider($this->getCachedHttpClient());

        // Close to the white house
        $result = $provider->reverseQuery(ReverseQuery::fromCoordinates(38.900206, -77.036991)->withLocale('en'));
        $this->assertWellFormattedResult($result);
    }

    public function testReverseQueryWithNoResults(): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        if (!$this->testReverse) {
            $this->markTestSkipped('Reverse geocoding address is not supported by this provider');
        }

        $provider = $this->createProvider($this->getCachedHttpClient());

        $result = $provider->reverseQuery(ReverseQuery::fromCoordinates(0, 0));
        $this->assertEquals(0, $result->count());
    }

    public function testGeocodeIpv4(): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        if (!$this->testIpv4) {
            $this->markTestSkipped('Geocoding IPv4 is not supported by this provider');
        }

        $provider = $this->createProvider($this->getCachedHttpClient());
        $result = $provider->geocodeQuery(GeocodeQuery::create('83.227.123.8')->withLocale('en'));
        $this->assertWellFormattedResult($result);
    }

    public function testGeocodeIpv6(): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        if (!$this->testIpv6) {
            $this->markTestSkipped('Geocoding IPv6 is not supported by this provider');
        }

        $provider = $this->createProvider($this->getCachedHttpClient());
        $result = $provider->geocodeQuery(GeocodeQuery::create('2001:0db8:0000:0042:0000:8a2e:0370:7334')->withLocale('en'));
        $this->assertWellFormattedResult($result);
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param GeocodeQuery|ReverseQuery $query
     * @param class-string<Exception>   $exceptionClass
     * @param ResponseInterface|null    $response
     * @param string                    $message
     */
    public function testExceptions($query, string $exceptionClass, ResponseInterface $response = null, string $message = ''): void
    {
        if (isset($this->skippedTests[__FUNCTION__])) {
            $this->markTestSkipped($this->skippedTests[__FUNCTION__]);
        }

        if (null === $response) {
            $provider = $this->createProvider($this->getCachedHttpClient());
        } else {
            $provider = $this->createProvider($this->getHttpClient($response));
        }

        $this->expectException($exceptionClass);
        if ($query instanceof ReverseQuery) {
            $provider->reverseQuery($query);
        } else {
            $provider->geocodeQuery($query);
        }
    }

    /**
     * @return array<array{GeocodeQuery|ReverseQuery, class-string<Exception>, Response, string}>
     */
    public function exceptionDataProvider(): array
    {
        $testData = [];

        if (!$this->testHttpProvider) {
            return $testData;
        }

        if ($this->testAddress) {
            $q = GeocodeQuery::create('foo');
            $testData[] = [$q, InvalidServerResponse::class, new Response(500), 'Server 500'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(400), 'Server 400'];
            $testData[] = [$q, InvalidCredentials::class, new Response(401), 'Invalid credentials response'];
            $testData[] = [$q, QuotaExceeded::class, new Response(429), 'Quota exceeded response'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(200), 'Empty response'];
        }

        if ($this->testReverse) {
            $q = ReverseQuery::fromCoordinates(0, 0);
            $testData[] = [$q, InvalidServerResponse::class, new Response(500), 'Server 500'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(400), 'Server 400'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(200), 'Empty response'];
            $testData[] = [$q, InvalidCredentials::class, new Response(401), 'Invalid credentials response'];
            $testData[] = [$q, QuotaExceeded::class, new Response(429), 'Quota exceeded response'];
        }

        if ($this->testIpv4) {
            $q = GeocodeQuery::create('123.123.123.123');
            $testData[] = [$q, InvalidServerResponse::class, new Response(500), 'Server 500'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(400), 'Server 400'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(200), 'Empty response'];
            $testData[] = [$q, InvalidCredentials::class, new Response(401), 'Invalid credentials response'];
            $testData[] = [$q, QuotaExceeded::class, new Response(429), 'Quota exceeded response'];
        }

        if ($this->testIpv6) {
            $q = GeocodeQuery::create('2001:0db8:0000:0042:0000:8a2e:0370:7334');
            $testData[] = [$q, InvalidServerResponse::class, new Response(500), 'Server 500'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(400), 'Server 400'];
            $testData[] = [$q, InvalidServerResponse::class, new Response(200), 'Empty response'];
            $testData[] = [$q, InvalidCredentials::class, new Response(401), 'Invalid credentials response'];
            $testData[] = [$q, QuotaExceeded::class, new Response(429), 'Quota exceeded response'];
        }

        return $testData;
    }

    /**
     * Make sure that a result for a Geocoder is well formatted. Be aware that even
     * a Location with no data may be well formatted.
     *
     * @param $result
     */
    private function assertWellFormattedResult(Collection $result): void
    {
        $this->assertInstanceOf(
            Collection::class,
            $result,
            'The result must be an instance of a Geocoder\Collection'
        );

        /** @var Location $location */
        foreach ($result as $location) {
            $this->assertInstanceOf(
                Location::class,
                $location,
                'All items in Geocoder\Collection must implement Geocoder\Location'
            );

            $this->assertInstanceOf(
                AdminLevelCollection::class,
                $location->getAdminLevels(),
                'Location::getAdminLevels MUST always return a AdminLevelCollection'
            );
            $arrayData = $location->toArray();
            $this->assertTrue(is_array($arrayData), 'Location::toArray MUST return an array.');
            $this->assertNotEmpty($arrayData, 'Location::toArray cannot be empty.');

            // Verify coordinates
            if (null !== $coords = $location->getCoordinates()) {
                $this->assertInstanceOf(
                    Coordinates::class,
                    $coords,
                    'Location::getCoordinates MUST always return a Coordinates or null'
                );

                // Using "assertNotEmpty" means that we can not have test code where coordinates is on equator or long = 0
                $this->assertNotEmpty($coords->getLatitude(), 'If coordinate object exists it cannot have an empty latitude.');
                $this->assertNotEmpty($coords->getLongitude(), 'If coordinate object exists it cannot have an empty longitude.');
            }

            // Verify bounds
            if (null !== $bounds = $location->getBounds()) {
                $this->assertInstanceOf(
                    Bounds::class,
                    $bounds,
                    'Location::getBounds MUST always return a Bounds or null'
                );

                // Using "assertNotEmpty" means that we can not have test code where coordinates is on equator or long = 0
                $this->assertNotEmpty($bounds->getSouth(), 'If bounds object exists it cannot have an empty values.');
                $this->assertNotEmpty($bounds->getWest(), 'If bounds object exists it cannot have an empty values.');
                $this->assertNotEmpty($bounds->getNorth(), 'If bounds object exists it cannot have an empty values.');
                $this->assertNotEmpty($bounds->getEast(), 'If bounds object exists it cannot have an empty values.');
            }

            // Check country
            if (null !== ($country = $location->getCountry())) {
                $this->assertInstanceOf(
                    Country::class,
                    $country,
                    'Location::getCountry MUST always return a Country or null'
                );
                $this->assertFalse(null === $country->getCode() && null === $country->getName(), 'Both code and name cannot be empty');

                if (null !== $country->getCode()) {
                    $this->assertNotEmpty(
                        $country->getCode(),
                        'The Country should not have an empty code.'
                    );
                }

                if (null !== $country->getName()) {
                    $this->assertNotEmpty(
                        $country->getName(),
                        'The Country should not have an empty name.'
                    );
                }
            }
        }
    }
}
