<?php
declare(strict_types=1);
/**
 * This file is part of the MageObsidian - ModernFrontend project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2024 Jeanmarcos Juarez
 */

namespace MageObsidian\ModernFrontend\Model\SchemaOrg\Builder;

class OrganizationBuilder
{
    use NodeValues;

    private const string DEFAULT_CONTACT_TYPE = 'customer support';

    public function build(array $data): array
    {
        $name = $this->text($data['name'] ?? null);
        $url = $this->text($data['url'] ?? null);
        if ($name === null || $url === null) {
            return [];
        }

        $node = ['@type' => 'Organization'];

        $id = $this->text($data['@id'] ?? null);
        if ($id !== null) {
            $node['@id'] = $id;
        }

        $node['name'] = $name;
        $node['url'] = $url;

        $logo = $this->text($data['logo'] ?? null);
        if ($logo !== null) {
            $node['logo'] = $logo;
        }

        $description = $this->text($data['description'] ?? null);
        if ($description !== null) {
            $node['description'] = $description;
        }

        $telephone = $this->text($data['telephone'] ?? null);
        if ($telephone !== null) {
            $node['telephone'] = $telephone;
        }

        $email = $this->text($data['email'] ?? null);
        if ($email !== null) {
            $node['email'] = $email;
        }

        $vatId = $this->text($data['vatID'] ?? null);
        if ($vatId !== null) {
            $node['vatID'] = $vatId;
        }

        $address = $this->buildAddress($data['address'] ?? null);
        if ($address !== []) {
            $node['address'] = $address;
        }

        $contactPoint = $this->buildContactPoint($data, $telephone, $email);
        if ($contactPoint !== []) {
            $node['contactPoint'] = $contactPoint;
        }

        $sameAs = $this->buildSameAs($data['sameAs'] ?? null);
        if ($sameAs !== []) {
            $node['sameAs'] = $sameAs;
        }

        return $node;
    }

    private function buildAddress(mixed $address): array
    {
        if (!is_array($address)) {
            return [];
        }

        $map = [
            'streetAddress' => 'streetAddress',
            'addressLocality' => 'addressLocality',
            'addressRegion' => 'addressRegion',
            'postalCode' => 'postalCode',
            'addressCountry' => 'addressCountry',
        ];

        $node = ['@type' => 'PostalAddress'];
        foreach ($map as $key => $property) {
            $value = $this->text($address[$key] ?? null);
            if ($value !== null) {
                $node[$property] = $value;
            }
        }

        return count($node) > 1 ? $node : [];
    }

    private function buildContactPoint(array $data, ?string $telephone, ?string $email): array
    {
        if ($telephone === null && $email === null) {
            return [];
        }

        $node = [
            '@type' => 'ContactPoint',
            'contactType' => $this->text($data['contactType'] ?? null) ?? self::DEFAULT_CONTACT_TYPE,
        ];

        if ($telephone !== null) {
            $node['telephone'] = $telephone;
        }

        if ($email !== null) {
            $node['email'] = $email;
        }

        $areaServed = $this->text($data['areaServed'] ?? null);
        if ($areaServed !== null) {
            $node['areaServed'] = $areaServed;
        }

        $availableLanguage = $this->text($data['availableLanguage'] ?? null);
        if ($availableLanguage !== null) {
            $node['availableLanguage'] = $availableLanguage;
        }

        return [$node];
    }

    private function buildSameAs(mixed $sameAs): array
    {
        if (is_string($sameAs)) {
            $sameAs = preg_split('/[\r\n,]+/', $sameAs) ?: [];
        }

        if (!is_array($sameAs)) {
            return [];
        }

        $uris = [];
        foreach ($sameAs as $candidate) {
            $uri = $this->text($candidate);
            if ($uri === null || !preg_match('#^https?://#i', $uri)) {
                continue;
            }
            $uris[$uri] = true;
        }

        return array_keys($uris);
    }
}
