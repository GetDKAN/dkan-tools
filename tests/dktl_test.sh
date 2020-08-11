#! /bin/sh
# file: examples/party_test.sh

export DKTL_TEST_DIR=`pwd`
export DKTL_TEST_PROJECT_NAME='testproject'
export DKTL_TEST_PROJECT_DIR="/tmp/${DKTL_TEST_PROJECT_NAME}"

oneTimeSetUp() {
    mkdir $DKTL_TEST_PROJECT_DIR
    chmod 755 $DKTL_TEST_PROJECT_DIR
}

setUp() {
    cd $DKTL_TEST_PROJECT_DIR
}

testUninitialized() {
    result=`dktl`
    assertContains "${result}" "DKTL is running outside of a DKTL project."
}

testDktlGetWithBadParameter() {
    result=`dktl init --drupal=foobar`
    assertContains "${result}" "[ERROR] version format not semantic.";
}

testDktlGetDrupalVersionLessThanMinimum()
{
    result=`init --drupal=8.7.1`;
    assertContains "${result}" "[ERROR] drupal version below minimal required."
}

testDktlGetSuccess() {
    result=`dktl init`
    assertContains "${result}" ' [OK] composer project created.'
}

testBringDown() {
    dktl down 
}

oneTimeTearDown() {
    # docker ps --filter name="${DKTL_TEST_PROJECT_NAME}*" -aq \
    #     | xargs docker stop | xargs docker rm -v
    # docker network disconnect "${DKTL_TEST_PROJECT_NAME}_default" dktl-proxy
    # docker network rm "${DKTL_TEST_PROJECT_NAME}_default"
    rm -rf $DKTL_TEST_PROJECT_DIR
}

# Load shUnit2.
. ./shunit2/shunit2