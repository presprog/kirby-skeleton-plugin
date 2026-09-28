import { defineConfig } from "vitest/config"

export default defineConfig({
  test: {
    css: false,
    environment: "happy-dom",
    include: ["panel/**/*.test.{js,ts}"],
    reporter: "dot",
    setupFiles: ["vitest.setup.js"]
  }
})
