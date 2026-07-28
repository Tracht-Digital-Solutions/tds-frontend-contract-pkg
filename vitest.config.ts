import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    // Scope the run to this package's own sources. Without an explicit
    // `include`, vitest's default `**/*.test.*` glob also picks up any
    // `.claude/worktrees/*/src/**` checkout sitting in the repo, so the run
    // silently reports another branch's tests as if they were this one's.
    include: ["src/**/*.test.ts"],
    environment: "node",
  },
});
