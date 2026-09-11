import "./index.css"
import ExampleField from "./fields/example.js"

panel.plugin("presprog/my-kirby-plugin", {
  fields: {
    "my-plugin-example": ExampleField
  }
})
