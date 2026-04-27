<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasModelHelpers
{
    /**
     * Get model's primary key value
     */
    public function getId()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get model's created timestamp
     */
    public function getCreatedAt()
    {
        return $this->getAttribute('created_at');
    }

    /**
     * Get model's updated timestamp
     */
    public function getUpdatedAt()
    {
        return $this->getAttribute('updated_at');
    }

    /**
     * Get model's deleted timestamp if using SoftDeletes
     */
    public function getDeletedAt()
    {
        return $this->getAttribute('deleted_at');
    }

    /**
     * Get user who created the model
     */
    public function getCreatedBy()
    {
        return $this->getAttribute('created_by');
    }

    /**
     * Get user who last updated the model
     */
    public function getUpdatedBy()
    {
        return $this->getAttribute('updated_by');
    }

    /**
     * Get user who deleted the model
     */
    public function getDeletedBy()
    {
        return $this->getAttribute('deleted_by');
    }

    /**
     * Set created_by attribute
     */
    public function setCreatedBy($value)
    {
        $this->setAttribute('created_by', $value);
        return $this;
    }

    /**
     * Set updated_by attribute
     */
    public function setUpdatedBy($value)
    {
        $this->setAttribute('updated_by', $value);
        return $this;
    }

    /**
     * Set deleted_by attribute
     */
    public function setDeletedBy($value)
    {
        $this->setAttribute('deleted_by', $value);
        return $this;
    }

    /**
     * Get model's status
     */
    public function getStatus()
    {
        return $this->getAttribute('status');
    }

    /**
     * Set model's status
     */
    public function setStatus($value)
    {
        $this->setAttribute('status', $value);
        return $this;
    }

    /**
     * Get model's name/title/label
     */
    public function getLabel()
    {
        $labelFields = ['name', 'title', 'label', 'description'];
        
        foreach ($labelFields as $field) {
            if ($this->getAttribute($field)) {
                return $this->getAttribute($field);
            }
        }

        return $this->getId();
    }

    /**
     * Get model's type/category
     */
    public function getType()
    {
        return $this->getAttribute('type');
    }

    /**
     * Set model's type/category
     */
    public function setType($value)
    {
        $this->setAttribute('type', $value);
        return $this;
    }

    /**
     * Get model's metadata
     */
    public function getMetadata()
    {
        $metadata = $this->getAttribute('metadata');
        return is_string($metadata) ? json_decode($metadata, true) : $metadata;
    }

    /**
     * Set model's metadata
     */
    public function setMetadata($value)
    {
        $this->setAttribute('metadata', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's settings
     */
    public function getSettings()
    {
        $settings = $this->getAttribute('settings');
        return is_string($settings) ? json_decode($settings, true) : $settings;
    }

    /**
     * Set model's settings
     */
    public function setSettings($value)
    {
        $this->setAttribute('settings', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's configuration
     */
    public function getConfig()
    {
        $config = $this->getAttribute('config');
        return is_string($config) ? json_decode($config, true) : $config;
    }

    /**
     * Set model's configuration
     */
    public function setConfig($value)
    {
        $this->setAttribute('config', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's data
     */
    public function getData()
    {
        $data = $this->getAttribute('data');
        return is_string($data) ? json_decode($data, true) : $data;
    }

    /**
     * Set model's data
     */
    public function setData($value)
    {
        $this->setAttribute('data', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's options
     */
    public function getOptions()
    {
        $options = $this->getAttribute('options');
        return is_string($options) ? json_decode($options, true) : $options;
    }

    /**
     * Set model's options
     */
    public function setOptions($value)
    {
        $this->setAttribute('options', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's parameters
     */
    public function getParameters()
    {
        $parameters = $this->getAttribute('parameters');
        return is_string($parameters) ? json_decode($parameters, true) : $parameters;
    }

    /**
     * Set model's parameters
     */
    public function setParameters($value)
    {
        $this->setAttribute('parameters', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's attributes
     */
    public function getAttributes()
    {
        $attributes = $this->getAttribute('attributes');
        return is_string($attributes) ? json_decode($attributes, true) : $attributes;
    }

    /**
     * Set model's attributes
     */
    public function setAttributes($value)
    {
        $this->setAttribute('attributes', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's properties
     */
    public function getProperties()
    {
        $properties = $this->getAttribute('properties');
        return is_string($properties) ? json_decode($properties, true) : $properties;
    }

    /**
     * Set model's properties
     */
    public function setProperties($value)
    {
        $this->setAttribute('properties', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's flags
     */
    public function getFlags()
    {
        $flags = $this->getAttribute('flags');
        return is_string($flags) ? json_decode($flags, true) : $flags;
    }

    /**
     * Set model's flags
     */
    public function setFlags($value)
    {
        $this->setAttribute('flags', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's tags
     */
    public function getTags()
    {
        $tags = $this->getAttribute('tags');
        return is_string($tags) ? json_decode($tags, true) : $tags;
    }

    /**
     * Set model's tags
     */
    public function setTags($value)
    {
        $this->setAttribute('tags', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's categories
     */
    public function getCategories()
    {
        $categories = $this->getAttribute('categories');
        return is_string($categories) ? json_decode($categories, true) : $categories;
    }

    /**
     * Set model's categories
     */
    public function setCategories($value)
    {
        $this->setAttribute('categories', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's groups
     */
    public function getGroups()
    {
        $groups = $this->getAttribute('groups');
        return is_string($groups) ? json_decode($groups, true) : $groups;
    }

    /**
     * Set model's groups
     */
    public function setGroups($value)
    {
        $this->setAttribute('groups', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's roles
     */
    public function getRoles()
    {
        $roles = $this->getAttribute('roles');
        return is_string($roles) ? json_decode($roles, true) : $roles;
    }

    /**
     * Set model's roles
     */
    public function setRoles($value)
    {
        $this->setAttribute('roles', is_array($value) ? json_encode($value) : $value);
        return $this;
    }

    /**
     * Get model's permissions
     */
    public function getPermissions()
    {
        $permissions = $this->getAttribute('permissions');
        return is_string($permissions) ? json_decode($permissions, true) : $permissions;
    }

    /**
     * Set model's permissions
     */
    public function setPermissions($value)
    {
        $this->setAttribute('permissions', is_array($value) ? json_encode($value) : $value);
        return $this;
    }
}
