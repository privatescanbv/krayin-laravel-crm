#!/bin/bash
JAVA_HOME=/Users/mark/Library/Java/JavaVirtualMachines/openjdk-19.0.1/Contents/Home
mvn clean install -DskipTests
#mvn install -DskipTests
cp ./target/keycloak-http-events.jar ../providers/

#sudo /usr/bin/xattr -c src/main/resources/META-INF/services/org.keycloak.events.EventListenerProviderFactory
