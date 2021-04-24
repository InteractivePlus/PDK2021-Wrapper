<?php
namespace InteractivePlus\PDK2021\OAuth;

use InteractivePlus\LibI18N\MultiLangValueProvider;

class OAuthScope{
    private $_scopeName;
    private MultiLangValueProvider $_scopeDisplayName;
    private MultiLangValueProvider $_scopeDescription;
    public function __construct(string $scopeName, MultiLangValueProvider $displayName, MultiLangValueProvider $description)
    {
        $this->_scopeName = $scopeName;
        $this->_scopeDisplayName = $displayName;
        $this->_scopeDescription = $description;
    }
    public function getScopeName() : string{
        return $this->_scopeName;
    }
    public function getDisplayName() : MultiLangValueProvider{
        return $this->_scopeDisplayName;
    }
    public function getDescription() : MultiLangValueProvider{
        return $this->_scopeDescription;
    }
    public function __toString() : string
    {
        return $this->_scopeName;
    }
}