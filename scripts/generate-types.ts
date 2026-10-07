#!/usr/bin/env bun
// Generates packages/sdk-php/src/Generated/Types.php from the OpenAPI spec
// (apps/api/src/openapi.yaml): one `@phpstan-type` array shape for each
// component schema, and `<Operation>Params` / `<Operation>Query` /
// `<Operation>Response` aliases for each operation.
//
//   bun packages/sdk-php/scripts/generate-types.ts          write the file
//   bun packages/sdk-php/scripts/generate-types.ts --check  exit 1 when the file is stale
//
// The script has no dependencies: it parses YAML with `Bun.YAML` (Bun 1.2.21
// or newer). Exit codes: 0 = ok, 1 = stale output, 2 = cannot run.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import path from "node:path";

type Node = { [key: string]: unknown };

const packageRoot = path.resolve(import.meta.dir, "..");
const specPath = path.resolve(packageRoot, "../../apps/api/src/openapi.yaml");
const outputPath = path.resolve(packageRoot, "src/Generated/Types.php");
const RUN_HINT = "bun packages/sdk-php/scripts/generate-types.ts";
const HTTP_METHODS = [
  "get",
  "put",
  "post",
  "delete",
  "options",
  "head",
  "patch",
  "trace",
];
const SCHEMA_REF = "#/components/schemas/";

function fail(message: string): never {
  console.error(`generate-types: ${message}`);
  process.exit(2);
}

function isNode(value: unknown): value is Node {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function nodes(value: unknown): Node[] {
  return Array.isArray(value) ? value.filter(isNode) : [];
}

function entries(value: unknown): [string, Node][] {
  if (!isNode(value)) {
    return [];
  }
  return Object.entries(value).filter((entry): entry is [string, Node] =>
    isNode(entry[1]),
  );
}

function strings(value: unknown): string[] {
  return Array.isArray(value)
    ? value.filter((item) => typeof item === "string")
    : [];
}

class Generator {
  private readonly schemas: Node;
  private readonly aliases = new Map<string, string>();
  private readonly schemaAliases = new Map<string, string>();

  constructor(private readonly spec: Node) {
    const components = isNode(spec.components) ? spec.components : {};
    this.schemas = isNode(components.schemas) ? components.schemas : {};
  }

  run(): string {
    // Reserve all the schema names first, so that a `$ref` to a schema that
    // comes later in the spec (or to the schema itself) resolves.
    for (const name of Object.keys(this.schemas)) {
      const alias = identifier(name);
      if ([...this.schemaAliases.values()].includes(alias)) {
        fail(`two component schemas map to the alias "${alias}"`);
      }
      this.schemaAliases.set(name, alias);
    }
    for (const [name, schema] of entries(this.schemas)) {
      this.define(this.schemaAliases.get(name) as string, this.type(schema, 0));
    }

    for (const [route, pathItem] of entries(this.spec.paths)) {
      for (const method of HTTP_METHODS) {
        const operation = pathItem[method];
        if (isNode(operation)) {
          this.operation(route, method, pathItem, operation);
        }
      }
    }

    return this.print();
  }

  private operation(
    route: string,
    method: string,
    pathItem: Node,
    operation: Node,
  ): void {
    if (
      typeof operation.operationId !== "string" ||
      operation.operationId === ""
    ) {
      fail(`${method.toUpperCase()} ${route} has no operationId`);
    }
    const base = pascal(operation.operationId);

    const body = this.jsonSchema(this.resolve(operation.requestBody));
    if (body !== null) {
      this.define(`${base}Params`, this.type(body, 0));
    }

    const query = new Map<string, { schema: unknown; required: boolean }>();
    for (const raw of [
      ...nodes(pathItem.parameters),
      ...nodes(operation.parameters),
    ]) {
      const parameter = this.resolve(raw);
      if (
        parameter !== null &&
        parameter.in === "query" &&
        typeof parameter.name === "string"
      ) {
        query.set(parameter.name, {
          schema: parameter.schema,
          required: parameter.required === true,
        });
      }
    }
    if (query.size > 0) {
      this.define(`${base}Query`, this.shape(query, 0));
    }

    const responses: string[] = [];
    for (const [status, raw] of entries(operation.responses)) {
      const response = this.resolve(raw);
      if (
        !/^2/.test(status) ||
        response === null ||
        !isNode(response.content)
      ) {
        continue;
      }
      const schema = this.jsonSchema(response);
      // A body that is not JSON (XML, bytes) comes back as a string.
      responses.push(schema === null ? "string" : this.type(schema, 0));
    }
    if (responses.length > 0) {
      this.define(`${base}Response`, union(responses));
    }
  }

  private define(alias: string, type: string): void {
    const existing = this.aliases.get(alias);
    if (existing === undefined) {
      // An operation alias that only repeats a schema alias of the same name
      // (`signup` -> `SignupResponse` -> schema `SignupResponse`) adds nothing.
      if (type !== alias) {
        this.aliases.set(alias, type);
      }
      return;
    }
    if (type !== alias && type !== existing) {
      fail(
        `the alias "${alias}" has two different definitions; rename a schema or an operationId`,
      );
    }
  }

  /** The JSON schema of a request body or a response; null when there is none. */
  private jsonSchema(holder: Node | null): Node | null {
    if (holder === null) {
      return null;
    }
    for (const [mediaType, media] of entries(holder.content)) {
      if (/^application\/(.+\+)?json\b/i.test(mediaType)) {
        return isNode(media.schema) ? media.schema : {};
      }
    }
    return null;
  }

  /** Follows a local `$ref` (any depth); returns the node itself when it has none. */
  private resolve(value: unknown): Node | null {
    let node = value;
    for (let depth = 0; depth < 32 && isNode(node); depth++) {
      if (typeof node.$ref !== "string") {
        return node;
      }
      const ref = node.$ref;
      if (!ref.startsWith("#/")) {
        fail(`unsupported $ref "${ref}": only local references are supported`);
      }
      node = ref
        .slice(2)
        .split("/")
        .map((part) => part.replace(/~1/g, "/").replace(/~0/g, "~"))
        .reduce<unknown>(
          (at, part) => (isNode(at) ? at[part] : undefined),
          this.spec,
        );
      if (!isNode(node)) {
        fail(`unresolved $ref "${ref}"`);
      }
    }
    return null;
  }

  /** The PHPDoc type of a schema. `depth` is the indentation level of the line it starts on. */
  private type(value: unknown, depth: number): string {
    if (value === true || !isNode(value)) {
      return "mixed";
    }
    const schema = value;
    const nullable = schema.nullable === true;

    if (typeof schema.$ref === "string") {
      return union([
        this.reference(schema.$ref, depth),
        ...(nullable ? ["null"] : []),
      ]);
    }
    if ("const" in schema) {
      return union([literal(schema.const), ...(nullable ? ["null"] : [])]);
    }
    if (Array.isArray(schema.enum)) {
      return union([
        ...schema.enum.map(literal),
        ...(nullable ? ["null"] : []),
      ]);
    }

    const parts: string[] = [];
    if (Array.isArray(schema.allOf)) {
      parts.push(this.allOf(schema, depth));
    } else if (Array.isArray(schema.oneOf) || Array.isArray(schema.anyOf)) {
      const members = [...nodes(schema.oneOf), ...nodes(schema.anyOf)];
      parts.push(...members.map((member) => this.type(member, depth)));
    } else if (Array.isArray(schema.type)) {
      for (const name of strings(schema.type)) {
        parts.push(this.typed({ ...schema, type: name }, depth));
      }
    } else {
      parts.push(this.typed(schema, depth));
    }
    if (nullable) {
      parts.push("null");
    }

    return union(parts);
  }

  private typed(schema: Node, depth: number): string {
    const kind =
      typeof schema.type === "string"
        ? schema.type
        : isNode(schema.properties) || "additionalProperties" in schema
          ? "object"
          : "items" in schema
            ? "array"
            : "";

    switch (kind) {
      case "string":
        return "string";
      case "integer":
        return "int";
      case "number":
        return "float|int";
      case "boolean":
        return "bool";
      case "null":
        return "null";
      case "array":
        return `list<${this.type(schema.items, depth)}>`;
      case "object":
        return this.object(schema, depth);
      default:
        return "mixed";
    }
  }

  private object(schema: Node, depth: number): string {
    const required = new Set(strings(schema.required));
    const properties = new Map<
      string,
      { schema: unknown; required: boolean }
    >();
    for (const [name, property] of Object.entries(
      isNode(schema.properties) ? schema.properties : {},
    )) {
      properties.set(name, { schema: property, required: required.has(name) });
    }

    const extra = schema.additionalProperties;
    if (properties.size === 0) {
      return `array<string, ${isNode(extra) ? this.type(extra, depth) : "mixed"}>`;
    }
    if (isNode(extra)) {
      // PHPDoc cannot say "these keys, and any other key with type T".
      console.error(
        "generate-types: properties + additionalProperties schema -> array<string, mixed>",
      );
      return "array<string, mixed>";
    }

    return this.shape(properties, depth);
  }

  private shape(
    properties: Map<string, { schema: unknown; required: boolean }>,
    depth: number,
  ): string {
    const lines = [...properties].map(
      ([name, property]) =>
        `${indent(depth + 1)}${key(name)}${property.required ? "" : "?"}: ${this.type(property.schema, depth + 1)},`,
    );

    return `array{\n${lines.join("\n")}\n${indent(depth)}}`;
  }

  /** Merges `allOf` into one shape when each part is an object with properties. */
  private allOf(schema: Node, depth: number): string {
    const merged = new Map<string, { schema: unknown; required: boolean }>();
    const required = new Set<string>();
    if (!this.collect(schema, merged, required, 0)) {
      // No PHPDoc type is correct for an intersection of types that are not
      // all objects; `mixed` is the safe one.
      console.error(
        "generate-types: allOf with a part that is not an object -> mixed",
      );
      return "mixed";
    }
    for (const [name, property] of merged) {
      property.required = required.has(name);
    }

    return this.shape(merged, depth);
  }

  private collect(
    value: unknown,
    properties: Map<string, { schema: unknown; required: boolean }>,
    required: Set<string>,
    level: number,
  ): boolean {
    const schema = this.resolve(value);
    if (schema === null || level > 16) {
      return false;
    }
    const parts = Array.isArray(schema.allOf) ? schema.allOf : [];
    const isObject = schema.type === "object" || isNode(schema.properties);
    if ((parts.length === 0 && !isObject) || schema.oneOf || schema.anyOf) {
      return false;
    }
    for (const part of parts) {
      if (!this.collect(part, properties, required, level + 1)) {
        return false;
      }
    }
    for (const [name, property] of Object.entries(
      isNode(schema.properties) ? schema.properties : {},
    )) {
      properties.set(name, { schema: property, required: false });
    }
    for (const name of strings(schema.required)) {
      required.add(name);
    }

    return true;
  }

  private reference(ref: string, depth: number): string {
    if (!ref.startsWith(SCHEMA_REF)) {
      return this.type(this.resolve({ $ref: ref }), depth);
    }
    const alias = this.schemaAliases.get(ref.slice(SCHEMA_REF.length));
    if (alias === undefined) {
      fail(`unresolved $ref "${ref}"`);
    }

    return alias;
  }

  private print(): string {
    const lines = [
      "<?php",
      "",
      "declare(strict_types=1);",
      "",
      "// Generated from apps/api/src/openapi.yaml by scripts/generate-types.ts. Do not edit.",
      "",
      "namespace PeppolSh\\Generated;",
      "",
      "/**",
      " * Array shapes of the peppol.sh API, for PHPStan and for IDE completion.",
      " * There is one alias for each schema of the OpenAPI spec. Each operation has",
      " * `<Operation>Params` (JSON request body), `<Operation>Query` (query",
      " * parameters), and `<Operation>Response` (2xx response) where they apply.",
      " *",
      " * To use an alias in your code, import it in the docblock of your class with",
      " * the PHPStan tag `phpstan-import-type <Alias> from \\PeppolSh\\Generated\\Types`.",
      " *",
      " * The API can add fields at any time: a shape lists the known keys only.",
    ];
    for (const [alias, type] of this.aliases) {
      const [first, ...rest] = type.split("\n");
      lines.push(
        " *",
        ` * @phpstan-type ${alias} ${first}`,
        ...rest.map((line) => ` * ${line}`),
      );
    }
    lines.push(
      " */",
      "final class Types",
      "{",
      "    private function __construct()",
      "    {",
      "    }",
      "}",
      "",
    );

    return lines.join("\n");
  }
}

function indent(depth: number): string {
  return "  ".repeat(depth);
}

/** Joins the members of a union; drops duplicates and puts `null` last. */
function union(parts: string[]): string {
  const unique = [...new Set(parts)];
  if (unique.includes("mixed")) {
    return "mixed";
  }
  const members = unique.filter((part) => part !== "null");
  if (members.length < unique.length) {
    members.push("null");
  }

  return members.length === 0 ? "mixed" : members.join("|");
}

function literal(value: unknown): string {
  if (typeof value === "string") {
    return `'${value.replace(/\\/g, "\\\\").replace(/'/g, "\\'")}'`;
  }
  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }

  return value === null ? "null" : "mixed";
}

function key(name: string): string {
  return /^[A-Za-z_][A-Za-z0-9_]*$/.test(name) ? name : literal(name);
}

function identifier(name: string): string {
  const clean = name.replace(/[^A-Za-z0-9_]/g, "_");

  return /^[0-9]/.test(clean) ? `_${clean}` : clean;
}

function pascal(operationId: string): string {
  return identifier(
    operationId
      .split(/[^A-Za-z0-9]+/)
      .filter((word) => word !== "")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(""),
  );
}

function main(): void {
  const check = process.argv.includes("--check");
  if (typeof Bun === "undefined" || typeof Bun.YAML?.parse !== "function") {
    fail("this script needs Bun 1.2.21 or newer (Bun.YAML)");
  }
  if (!existsSync(specPath)) {
    fail(`no OpenAPI spec at ${specPath}; run this script in the monorepo`);
  }

  const spec = Bun.YAML.parse(readFileSync(specPath, "utf-8"));
  if (!isNode(spec) || !isNode(spec.paths)) {
    fail(`${specPath} is not an OpenAPI document`);
  }
  const generated = new Generator(spec).run();

  if (!check) {
    mkdirSync(path.dirname(outputPath), { recursive: true });
    writeFileSync(outputPath, generated);
    console.log(`Wrote ${path.relative(process.cwd(), outputPath)}`);
    return;
  }

  const committed = existsSync(outputPath)
    ? readFileSync(outputPath, "utf-8")
    : "";
  if (committed !== generated) {
    console.error(
      `packages/sdk-php/src/Generated/Types.php does not match apps/api/src/openapi.yaml.\nrun: ${RUN_HINT}`,
    );
    process.exit(1);
  }
  console.log("packages/sdk-php/src/Generated/Types.php matches the spec.");
}

main();
