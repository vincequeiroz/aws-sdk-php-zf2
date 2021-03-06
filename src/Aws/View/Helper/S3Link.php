<?php
/**
 * Copyright 2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Aws\View\Helper;

use Aws\Common\Aws;
use Aws\S3\BucketStyleListener;
use Aws\S3\S3Client;
use Aws\View\Exception\InvalidDomainNameException;
use Guzzle\Common\Event;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper that can render a link to a S3 object. It can also create signed URLs
 */
class S3Link extends AbstractHelper
{
    /**
     * @var S3Client
     */
    protected $client;

    /**
     * @var bool
     */
    protected $useSsl = true;

    /**
     * @var string
     */
    protected $defaultBucket = '';

    /**
     * Constuctor
     *
     * @param S3Client $client
     */
    public function __construct(S3Client $client)
    {
        $this->client = $client;
    }

    /**
     * Set if HTTPS should be used for generating URLs
     *
     * @param bool $useSsl
     */
    public function setUseSsl($useSsl)
    {
        $this->useSsl = (bool) $useSsl;
    }

    /**
     * Get if HTTPS should be used for generating URLs
     *
     * @return bool
     */
    public function getUseSsl()
    {
        return $this->useSsl;
    }

    /**
     * Set the default bucket to use if none is provided
     *
     * @param string $defaultBucket
     * @return void
     */
    public function setDefaultBucket($defaultBucket)
    {
        $this->defaultBucket = (string) $defaultBucket;
    }

    /**
     * Get the default bucket to use if none is provided
     *
     * @return string
     */
    public function getDefaultBucket()
    {
        return $this->defaultBucket;
    }

    /**
     * Create a link to a S3 object from a bucket. If expiration is not empty, then it is used to create
     * a signed URL
     *
     * @param  string     $object The object name (full path)
     * @param  string     $bucket The bucket name
     * @param  string|int $expiration The Unix timestamp to expire at or a string that can be evaluated by strtotime
     * @throws InvalidDomainNameException
     * @return string
     */
    public function __invoke($object, $bucket = '', $expiration = '')
    {
        $bucket = trim($bucket ?: $this->getDefaultBucket(), '/');
        if (empty($bucket)) {
            throw new InvalidDomainNameException('An empty bucket name was given');
        }

        // Create a command representing the get request
        // Using a command will make sure the configured regional endpoint is used
        $command = $this->client->getCommand('GetObject', array(
            'Bucket' => $bucket,
            'Key'    => $object,
        ));

        // Instead of executing the command, retrieve the request and make sure the scheme is set to what was specified
        $request = $command->prepare()->setScheme($this->useSsl ? 'https' : 'http')->setPort(null);

        // Ensure that the correct bucket URL style (virtual or path) is used based on the bucket name
        // This addresses a bug in versions of the SDK less than or equal to 2.3.4
        if (version_compare(Aws::VERSION, '2.3.4', '<=') && strpos($request->getHost(), $bucket) === false) {
            $bucketStyleListener = new BucketStyleListener();
            $bucketStyleListener->onCommandBeforeSend(new Event(array('command' => $command)));
        }

        if ($expiration) {
            return $this->client->createPresignedUrl($request, $expiration);
        } else {
            return $request->getUrl();
        }
    }
}
