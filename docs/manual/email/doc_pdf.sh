#!/bin/bash

# Creates manual for the customers of Lento. This bash file isn't executed in CI, so needs to be done manually.
# output: See ./modules/application/pages/manual.pdf

asciidoctor-pdf ./email-handleiding.adoc -o ./email-handleiding.pdf
