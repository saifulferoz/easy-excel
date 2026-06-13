<?php

declare(strict_types=1);

namespace EasyExcel\Compat\Document;

use EasyExcel\Compat\Spreadsheet;
use EasyExcel\Native;

/**
 * Document metadata (docProps/core.xml + company in app.xml). Setters push
 * the accumulated state; excelize exposes no manager field, so setManager
 * is accepted but only kept PHP-side (COMPAT.md).
 */
class Properties
{
    private array $props = [];

    private string $manager = '';

    public function __construct(private Spreadsheet $spreadsheet)
    {
    }

    public function setTitle(string $title): static
    {
        return $this->set('title', $title);
    }

    public function getTitle(): string
    {
        return $this->props['title'] ?? '';
    }

    public function setSubject(string $subject): static
    {
        return $this->set('subject', $subject);
    }

    public function getSubject(): string
    {
        return $this->props['subject'] ?? '';
    }

    public function setCreator(string $creator): static
    {
        return $this->set('creator', $creator);
    }

    public function getCreator(): string
    {
        return $this->props['creator'] ?? '';
    }

    public function setLastModifiedBy(string $by): static
    {
        return $this->set('lastModifiedBy', $by);
    }

    public function setDescription(string $description): static
    {
        return $this->set('description', $description);
    }

    public function getDescription(): string
    {
        return $this->props['description'] ?? '';
    }

    public function setKeywords(string $keywords): static
    {
        return $this->set('keywords', $keywords);
    }

    public function setCategory(string $category): static
    {
        return $this->set('category', $category);
    }

    public function setCompany(string $company): static
    {
        return $this->set('company', $company);
    }

    public function setManager(string $manager): static
    {
        $this->manager = $manager; // PHP-side only, see class doc

        return $this;
    }

    public function getManager(): string
    {
        return $this->manager;
    }

    private function set(string $key, string $value): static
    {
        $this->props[$key] = $value;
        Native::docProps($this->spreadsheet->getHandle(), $this->props);

        return $this;
    }
}
