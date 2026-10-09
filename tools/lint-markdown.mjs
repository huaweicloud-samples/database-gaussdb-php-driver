/** Lint tracked Markdown using Node.js 22+ and npm ci; never modifies files. */
import { execFileSync } from "node:child_process";
import { lint } from "markdownlint/promise";

// Git supplies exact tracked paths without a glob parser or traversal dependencies.
const files = execFileSync("git", ["ls-files", "-z", "--", "*.md"], {
  encoding: "utf8"
}).split("\0").filter(Boolean);
if (files.length === 0) {
  throw new Error("No tracked Markdown files found");
}
const results = await lint({
  files,
  config: {
    default: true,
    MD013: false,
    MD024: { siblings_only: true },
    MD060: false
  }
});
const errors = Object.entries(results).flatMap(([file, violations]) =>
  violations.map((violation) =>
    `${file}:${violation.lineNumber} ${violation.ruleNames[0]} ${violation.ruleDescription}`
  )
);
if (errors.length) {
  console.error(errors.join("\n"));
  process.exitCode = 1;
} else {
  console.log(`Markdown: ${files.length} files, no issues`);
}
