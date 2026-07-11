# Agent integration

`new` and `package` use `laravel/agent-detector` to recognize supported AI-agent environments. In that mode prompts are disabled and the command emits one JSON result to standard output.

A successful result includes `success: true`, the resolved project/package name, and directory. A failure includes `success: false` and, when available, an error, a log-file path, and a sanitized log tail. Consumers should parse the final JSON line and preserve the log location for debugging.

Use non-interactive command lines in agents:

```sh
laravel new inventory --react --database=sqlite --pest --no-node --no-interaction
```

`laravel web` removes agent-detection variables from its child `new` process so the browser receives normal progress output instead of agent JSON.