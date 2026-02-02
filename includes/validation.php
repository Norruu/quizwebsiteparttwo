<?php
/**
 * Input Validation Functions
 * Centralized validation for all user inputs
 */

class Validator {
    private array $errors = [];
    private array $data = [];
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Get validation errors
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error message
     */
    public function firstError(): ?string {
        return $this->errors[array_key_first($this->errors)] ?? null;
    }
    
    /**
     * Add custom error
     */
    public function addError(string $field, string $message): self {
        $this->errors[$field] = $message;
        return $this;
    }
    
    /**
     * Required field validation
     */
    public function required(string $field, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = "$label is required.";
        }
        
        return $this;
    }
    
    /**
     * Email validation
     */
    public function email(string $field, string $label = 'Email'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = "$label must be a valid email address.";
            }
        }
        
        return $this;
    }
    
    /**
     * Minimum length validation
     */
    public function minLength(string $field, int $min, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = "$label must be at least $min characters.";
        }
        
        return $this;
    }
    
    /**
     * Maximum length validation
     */
    public function maxLength(string $field, int $max, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = "$label must not exceed $max characters.";
        }
        
        return $this;
    }
    
    /**
     * Password strength validation
     */
    public function password(string $field, string $label = 'Password'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $password = $this->data[$field];
            
            if (strlen($password) < 8) {
                $this->errors[$field] = "$label must be at least 8 characters.";
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $this->errors[$field] = "$label must contain at least one uppercase letter.";
            } elseif (!preg_match('/[a-z]/', $password)) {
                $this->errors[$field] = "$label must contain at least one lowercase letter.";
            } elseif (!preg_match('/[0-9]/', $password)) {
                $this->errors[$field] = "$label must contain at least one number.";
            } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
                $this->errors[$field] = "$label must contain at least one special character.";
            }
        }
        
        return $this;
    }
    
    /**
     * Confirm field matches another field
     */
    public function confirmed(string $field, string $confirmField, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && isset($this->data[$confirmField])) {
            if ($this->data[$field] !== $this->data[$confirmField]) {
                $this->errors[$confirmField] = "$label confirmation does not match.";
            }
        }
        
        return $this;
    }
    
    /**
     * Username validation
     */
    public function username(string $field, string $label = 'Username'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $this->data[$field])) {
                $this->errors[$field] = "$label must be 3-30 characters and contain only letters, numbers, and underscores.";
            }
        }
        
        return $this;
    }
    
    /**
     * Numeric validation
     */
    public function numeric(string $field, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "$label must be a number.";
        }
        
        return $this;
    }
    
    /**
     * Integer validation
     */
    public function integer(string $field, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && filter_var($this->data[$field], FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = "$label must be an integer.";
        }
        
        return $this;
    }
    
    /**
     * Minimum value validation
     */
    public function min(string $field, int $min, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && $this->data[$field] < $min) {
            $this->errors[$field] = "$label must be at least $min.";
        }
        
        return $this;
    }
    
    /**
     * Maximum value validation
     */
    public function max(string $field, int $max, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && $this->data[$field] > $max) {
            $this->errors[$field] = "$label must not exceed $max.";
        }
        
        return $this;
    }
    
    /**
     * In array validation
     */
    public function in(string $field, array $allowed, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed)) {
            $this->errors[$field] = "$label must be one of: " . implode(', ', $allowed);
        }
        
        return $this;
    }
    
    /**
     * Unique validation (database check)
     */
    public function unique(string $field, string $table, string $column = null, ?int $exceptId = null, string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        $column = $column ?? $field;
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $sql = "SELECT id FROM $table WHERE $column = ?";
            $params = [$this->data[$field]];
            
            if ($exceptId) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }
            
            $exists = Database::fetch($sql, $params);
            
            if ($exists) {
                $this->errors[$field] = "$label is already taken.";
            }
        }
        
        return $this;
    }
    
    /**
     * Exists validation (database check)
     */
    public function exists(string $field, string $table, string $column = 'id', string $label = null): self {
        $label = $label ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $exists = Database::fetch(
                "SELECT id FROM $table WHERE $column = ?",
                [$this->data[$field]]
            );
            
            if (!$exists) {
                $this->errors[$field] = "$label does not exist.";
            }
        }
        
        return $this;
    }
    
    /**
     * Get validated value
     */
    public function getValue(string $field, $default = null) {
        return $this->data[$field] ?? $default;
    }
    
    /**
     * Get all validated data
     */
    public function validated(): array {
        return $this->data;
    }
}

/**
 * Quick validation helper
 */
function validate(array $data): Validator {
    return new Validator($data);
}

/**
 * Sanitize input string
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize array of inputs
 */
function sanitizeArray(array $inputs): array {
    return array_map(function($value) {
        return is_string($value) ? sanitize($value) : $value;
    }, $inputs);
}