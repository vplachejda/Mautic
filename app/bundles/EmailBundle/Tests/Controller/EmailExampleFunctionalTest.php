<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Controller;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\Mapping\MappingException;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Tests\Helper\Transport\SmtpTransport;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Mailer;

class EmailExampleFunctionalTest extends MauticMysqlTestCase
{
    private SmtpTransport $transport;

    protected function setUp(): void
    {
        $this->configParams['mailer_spool_type'] = 'file';
        parent::setUp();
        $this->setUpMailer();
    }

    private function setUpMailer(): void
    {
        $mailHelper = self::$container->get('mautic.helper.mailer');
        $transport  = new SmtpTransport();
        $mailer     = new Mailer($transport);
        $this->setPrivateProperty($mailHelper, 'mailer', $mailer);
        $this->setPrivateProperty($mailHelper, 'transport', $transport);

        $this->transport  = $transport;
    }

    private function setPrivateProperty(object $object, string $property, $value): void
    {
        $reflector = new \ReflectionProperty(get_class($object), $property);
        $reflector->setAccessible(true);
        $reflector->setValue($object, $value);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws MappingException
     */
    public function testSendExampleEmailWithContact(): void
    {
        $lead  = $this->createLead();
        $email = $this->createEmail();
        $this->em->flush();
        $this->em->clear();

        $crawler     = $this->client->request(Request::METHOD_GET, "/s/emails/sendExample/{$email->getId()}");
        $formCrawler = $crawler->filter('form[name=example_send]');
        self::assertSame(1, $formCrawler->count());
        $form = $formCrawler->form();
        $form->setValues([
            'example_send[emails][list][0]' => 'admin@yoursite.com',
        ]);
        $this->client->submit($form);

        $message = $this->transport->sentMessage;

        // Asserting email data
        self::assertSame('admin@yoursite.com', key($message->getTo()));
        self::assertStringContainsString('Email subject for Test Lead, living in Lane 11, Near Post Office, Pune, India. Contact number: 012', $message->getSubject());
    }

    /**
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testSendExampleEmailWithOutContact(): void
    {
        $email = $this->createEmail();
        $this->em->flush();
        $this->em->clear();

        $crawler     = $this->client->request(Request::METHOD_GET, "/s/emails/sendExample/{$email->getId()}");
        $formCrawler = $crawler->filter('form[name=example_send]');
        self::assertSame(1, $formCrawler->count());
        $form = $formCrawler->form();
        $form->setValues(['example_send[emails][list][0]' => 'admin@yoursite.com']);
        $this->client->submit($form);

        $message = $this->transport->sentMessage;

        // Asserting email data
        self::assertSame('admin@yoursite.com', key($message->getTo()));
        self::assertStringContainsString('Email subject for [First Name] [Last Name], living in [Address Line 1], [Address Line 2], [City], [Country]. Contact number: [Mobile]', $message->getSubject());
    }

    /**
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testSendExampleEmailForDynamicContentVariantsWithCustomFieldWithNoContact(): void
    {
        // Create custom field
        $this->client->request(
            'POST',
            '/api/fields/contact/new',
            [
                'label'      => 'bool',
                'type'       => 'boolean',
                'properties' => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ],
            ]
        );
        $response = $this->client->getResponse()->getContent();
        self::assertSame(201, $this->client->getResponse()->getStatusCode(), $response);
        self::assertJson($response);

        // Create email with dynamic content variant
        $email          = $this->createEmail();
        $dynamicContent = [
            [
                'tokenName' => 'Dynamic Content 1',
                'content'   => '<p>Default Dynamic Content</p>',
                'filters'   => [
                    [
                        'content' => null,
                        'filters' => [],
                    ],
                ],
            ],
            [
                'tokenName' => 'Dynamic Content 2',
                'content'   => '<p>Default Dynamic Content</p>',
                'filters'   => [
                    [
                        'content' => '<p>Variant 1 Dynamic Content</p>',
                        'filters' => [
                            [
                                'glue'     => 'and',
                                'field'    => 'bool',
                                'object'   => 'lead',
                                'type'     => 'boolean',
                                'filter'   => '1',
                                'display'  => null,
                                'operator' => '=',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $email->setCustomHtml('<div>{dynamiccontent="Dynamic Content 2"}</div>');
        $email->setDynamicContent($dynamicContent);
        $this->em->flush();
        $this->em->clear();

        $crawler     = $this->client->request(Request::METHOD_GET, "/s/emails/sendExample/{$email->getId()}");
        $formCrawler = $crawler->filter('form[name=example_send]');
        self::assertSame(1, $formCrawler->count());
        $form = $formCrawler->form();
        $form->setValues(['example_send[emails][list][0]' => 'admin@yoursite.com']);
        $this->client->submit($form);
        $message = $this->transport->sentMessage;

        // Asserting email data
        self::assertSame('admin@yoursite.com', key($message->getTo()));
        self::assertStringContainsString('Email subject', $message->getSubject());
    }

    /**
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testSendExampleEmailForDynamicContentVariantsWithCustomFieldWithMatchFilterContact(): void
    {
        // Create custom field
        $this->client->request(
            'POST',
            '/api/fields/contact/new',
            [
                'label'      => 'bool',
                'type'       => 'boolean',
                'properties' => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ],
            ]
        );
        $response = $this->client->getResponse()->getContent();
        self::assertSame(201, $this->client->getResponse()->getStatusCode(), $response);
        self::assertJson($response);

        // Create email with dynamic content variant
        $email          = $this->createEmail();
        $dynamicContent = [
            [
                'tokenName' => 'Dynamic Content 1',
                'content'   => '<p>Default Dynamic Content</p>',
                'filters'   => [
                    [
                        'content' => null,
                        'filters' => [],
                    ],
                ],
            ],
            [
                'tokenName' => 'Dynamic Content 2',
                'content'   => '<p>Default Dynamic Content</p>',
                'filters'   => [
                    [
                        'content' => '<p>Variant 1 Dynamic Content</p>',
                        'filters' => [
                            [
                                'glue'     => 'and',
                                'field'    => 'bool',
                                'object'   => 'lead',
                                'type'     => 'boolean',
                                'filter'   => '1',
                                'display'  => null,
                                'operator' => '=',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $email->setCustomHtml('<div>{dynamiccontent="Dynamic Content 2"}</div>');
        $email->setDynamicContent($dynamicContent);
        $this->em->flush();
        $this->em->clear();

        // Create some contacts
        $this->client->request(
            'POST',
            '/api/contacts/batch/new',
            [
                [
                    'firstname' => 'John',
                    'lastname'  => 'A',
                    'email'     => 'john.a@email.com',
                ],
            ]
        );
        self::assertSame(
            201,
            $this->client->getResponse()->getStatusCode(),
            $this->client->getResponse()->getContent()
        );
        $contacts = json_decode($this->client->getResponse()->getContent(), true);

        $crawler     = $this->client->request(Request::METHOD_GET, "/s/emails/sendExample/{$email->getId()}");
        $formCrawler = $crawler->filter('form[name=example_send]');
        self::assertSame(1, $formCrawler->count());
        $form = $formCrawler->form();
        $form->setValues([
            'example_send[emails][list][0]' => 'admin@yoursite.com',
        ]);
        $this->client->submit($form);
        $message = $this->transport->sentMessage;

        // Asserting email data
        self::assertSame('admin@yoursite.com', key($message->getTo()));
        self::assertStringContainsString('Email subject', $message->getSubject());
    }

    /**
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function testSendExampleEmailForDynamicContentVariantsWithCustomFieldWithNoMatchFilterContact(): void
    {
        // Create custom field
        $this->client->request(
            'POST',
            '/api/fields/contact/new',
            [
                'label'      => 'bool',
                'type'       => 'boolean',
                'properties' => [
                    'no'  => 'No',
                    'yes' => 'Yes',
                ],
            ]
        );
        $response = $this->client->getResponse()->getContent();
        self::assertSame(201, $this->client->getResponse()->getStatusCode(), $response);
        self::assertJson($response);

        // Create email with dynamic content variant
        $email          = $this->createEmail();
        $dynamicContent = [
            [
                'tokenName' => 'Dynamic Content 1',
                'content'   => '<p>Default Dynamic Content</p>',
                'filters'   => [
                    [
                        'content' => null,
                        'filters' => [],
                    ],
                ],
            ],
            [
                'tokenName' => 'Dynamic Content 2',
                'content'   => '<p>Default Dynamic Content</p>',
                'filters'   => [
                    [
                        'content' => '<p>Variant 1 Dynamic Content</p>',
                        'filters' => [
                            [
                                'glue'     => 'and',
                                'field'    => 'bool',
                                'object'   => 'lead',
                                'type'     => 'boolean',
                                'filter'   => '1',
                                'display'  => null,
                                'operator' => '=',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $email->setCustomHtml('<div>{dynamiccontent="Dynamic Content 2"}</div>');
        $email->setDynamicContent($dynamicContent);
        $this->em->flush();
        $this->em->clear();

        // Create some contacts
        $this->client->request(
            'POST',
            '/api/contacts/batch/new',
            [
                [
                    'firstname' => 'John',
                    'lastname'  => 'A',
                    'email'     => 'john.a@email.com',
                    'bool'      => false,
                ],
            ]
        );
        self::assertSame(
            201,
            $this->client->getResponse()->getStatusCode(),
            $this->client->getResponse()->getContent()
        );
        $contacts = json_decode($this->client->getResponse()->getContent(), true);

        $crawler     = $this->client->request(Request::METHOD_GET, "/s/emails/sendExample/{$email->getId()}");
        $formCrawler = $crawler->filter('form[name=example_send]');
        self::assertSame(1, $formCrawler->count());
        $form = $formCrawler->form();
        $form->setValues([
            'example_send[emails][list][0]' => 'admin@yoursite.com',
        ]);
        $this->client->submit($form);
        $message = $this->transport->sentMessage;

        // Asserting email data
        self::assertSame('admin@yoursite.com', key($message->getTo()));
        self::assertStringContainsString('Email subject', $message->getSubject());
    }

    private function createEmail(): Email
    {
        $email = new Email();
        $email->setDateAdded(new \DateTime());
        $email->setName('Email name');
        $email->setSubject('Email subject for {contactfield=firstname} {contactfield=lastname}, living in {contactfield=address1}, {contactfield=address2}, {contactfield=city}, {contactfield=country}. Contact number: {contactfield=mobile}');
        $email->setTemplate('Blank');
        $email->setCustomHtml('Email body for %7Bcontactfield=firstname%7D %7Bcontactfield%3Dlastname%7D, living in {contactfield=address1}, {contactfield=address2}, {contactfield=city}, {contactfield=country}. Contact number: {contactfield=mobile}');
        $this->em->persist($email);

        return $email;
    }

    private function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setFirstname('Test');
        $lead->setLastname('Lead');
        $lead->setMobile('012');
        $lead->setAddress1('Lane 11');
        $lead->setAddress2('Near Post Office');
        $lead->setCity('Pune');
        $lead->setCountry('India');
        $lead->setEmail('test@domain.tld');
        $this->em->persist($lead);

        return $lead;
    }
}
